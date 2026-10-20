<?php

namespace App\Http\Controllers;

use App\Http\Requests\HouseholdSurveyRequest;
use App\Models\Household;
use App\Models\HouseholdSurvey;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HouseholdSurveyController extends Controller
{
    /** Private disk (storage/app/private): photos are only reachable through photo(). */
    private const DISK = 'local';

    public function index(Request $request): View
    {
        $term = trim((string) $request->input('q'));

        $surveys = HouseholdSurvey::query()
            ->with(['household:id,household_code,head_name', 'surveyor:id,name'])
            ->when($term !== '', fn ($query) => $query->whereHas('household', fn ($household) => $household
                ->where(fn ($q) => $q
                    ->where('household_code', 'like', "%{$term}%")
                    ->orWhere('head_name', 'like', "%{$term}%"))))
            ->when($request->filled('status'), fn ($query) => $query->where('validation_status', $request->input('status')))
            ->when($request->filled('sorting'), fn ($query) => $query->where('sorting_status', $request->input('sorting')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('survey_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('survey_date', '<=', $request->input('to')))
            ->orderByDesc('survey_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Totals per validation status for the quick-filter buttons (ignores the other filters).
        $counts = HouseholdSurvey::query()
            ->selectRaw('validation_status, count(*) as total')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        return view('household-surveys.index', compact('surveys', 'counts', 'term'));
    }

    public function create(Request $request): View
    {
        $survey = new HouseholdSurvey;
        $survey->survey_date = today();
        $survey->household_id = $request->integer('household') ?: null;

        return view('household-surveys.form', [
            'survey' => $survey,
            'householdOptions' => $this->householdOptions(),
        ]);
    }

    public function store(HouseholdSurveyRequest $request): RedirectResponse
    {
        $survey = new HouseholdSurvey(Arr::only($request->validated(), ['household_id', 'survey_date', 'sorting_status', 'notes']));
        $survey->surveyed_by = $request->user()->id;

        if ($request->hasFile('photo')) {
            $survey->photo_path = $request->file('photo')->store('survey-photos', self::DISK);
        }

        $survey->save();

        return redirect()->route('household-surveys.show', $survey)
            ->with('status', __('Survey was saved and is waiting for validation.'));
    }

    public function show(HouseholdSurvey $householdSurvey): View
    {
        $householdSurvey->load(['household', 'surveyor', 'validator']);

        return view('household-surveys.show', ['survey' => $householdSurvey]);
    }

    public function edit(Request $request, HouseholdSurvey $householdSurvey): View
    {
        $this->authorizeModify($householdSurvey, $request->user());

        return view('household-surveys.form', [
            'survey' => $householdSurvey->load('household'),
            'householdOptions' => [],
        ]);
    }

    public function update(HouseholdSurveyRequest $request, HouseholdSurvey $householdSurvey): RedirectResponse
    {
        $householdSurvey->fill(Arr::only($request->validated(), ['survey_date', 'sorting_status', 'notes']));

        if ($request->hasFile('photo')) {
            $this->deletePhoto($householdSurvey);
            $householdSurvey->photo_path = $request->file('photo')->store('survey-photos', self::DISK);
        } elseif ($request->boolean('remove_photo')) {
            $this->deletePhoto($householdSurvey);
            $householdSurvey->photo_path = null;
        }

        // Editing a rejected survey resubmits it for validation.
        $resubmitted = $householdSurvey->isRejected();
        if ($resubmitted) {
            $householdSurvey->forceFill([
                'validation_status' => HouseholdSurvey::PENDING,
                'validated_by' => null,
                'validated_at' => null,
                'validation_notes' => null,
            ]);
        }

        $householdSurvey->save();

        return redirect()->route('household-surveys.show', $householdSurvey)
            ->with('status', $resubmitted
                ? __('Survey was updated and resubmitted for validation.')
                : __('Survey was updated.'));
    }

    public function destroy(Request $request, HouseholdSurvey $householdSurvey): RedirectResponse
    {
        $this->authorizeModify($householdSurvey, $request->user());

        $this->deletePhoto($householdSurvey);
        $householdSurvey->delete();

        return redirect()->route('household-surveys.index')->with('status', __('Survey was deleted.'));
    }

    // ---- Validation workflow (route middleware: can:validate-surveys) -------------------

    public function approve(Request $request, HouseholdSurvey $householdSurvey): RedirectResponse
    {
        $data = $request->validate(['validation_notes' => ['nullable', 'string', 'max:500']]);

        if (! $householdSurvey->isPending()) {
            return back()->with('error', __('Only pending surveys can be validated or rejected.'));
        }

        DB::transaction(function () use ($householdSurvey, $data, $request) {
            $householdSurvey->forceFill([
                'validation_status' => HouseholdSurvey::VALIDATED,
                'validated_by' => $request->user()->id,
                'validated_at' => now(),
                'validation_notes' => $data['validation_notes'] ?? null,
            ])->save();

            $householdSurvey->household->refreshSortingStatus();
        });

        return back()->with('status', __('Survey was validated.'));
    }

    public function reject(Request $request, HouseholdSurvey $householdSurvey): RedirectResponse
    {
        $data = $request->validate(['validation_notes' => ['required', 'string', 'max:500']]);

        if (! $householdSurvey->isPending()) {
            return back()->with('error', __('Only pending surveys can be validated or rejected.'));
        }

        $householdSurvey->forceFill([
            'validation_status' => HouseholdSurvey::REJECTED,
            'validated_by' => $request->user()->id,
            'validated_at' => now(),
            'validation_notes' => $data['validation_notes'],
        ])->save();

        return back()->with('status', __('Survey was rejected.'));
    }

    /** Undo a validation or rejection; the household status is recalculated. */
    public function reopen(HouseholdSurvey $householdSurvey): RedirectResponse
    {
        if ($householdSurvey->isPending()) {
            return back()->with('error', __('This survey is already pending.'));
        }

        DB::transaction(function () use ($householdSurvey) {
            $householdSurvey->forceFill([
                'validation_status' => HouseholdSurvey::PENDING,
                'validated_by' => null,
                'validated_at' => null,
                'validation_notes' => null,
            ])->save();

            $householdSurvey->household->refreshSortingStatus();
        });

        return back()->with('status', __('Survey was reopened.'));
    }

    // ---- Photo ------------------------------------------------------------------------

    public function photo(HouseholdSurvey $householdSurvey): StreamedResponse
    {
        abort_unless(
            $householdSurvey->photo_path && Storage::disk(self::DISK)->exists($householdSurvey->photo_path),
            404
        );

        return Storage::disk(self::DISK)->response($householdSurvey->photo_path);
    }

    // ---- Helpers ----------------------------------------------------------------------

    private function authorizeModify(HouseholdSurvey $survey, User $user): void
    {
        abort_unless($survey->canBeModifiedBy($user), 403);
    }

    private function deletePhoto(HouseholdSurvey $survey): void
    {
        if ($survey->photo_path) {
            Storage::disk(self::DISK)->delete($survey->photo_path);
        }
    }

    /** @return array<int, string> household id => "RMH-0001 - Head name" */
    private function householdOptions(): array
    {
        return Household::query()
            ->orderBy('household_code')
            ->get(['id', 'household_code', 'head_name'])
            ->mapWithKeys(fn (Household $h) => [$h->id => $h->household_code.' - '.$h->head_name])
            ->all();
    }
}
