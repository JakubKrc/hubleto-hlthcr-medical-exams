<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\JobHasHazard;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Hazard;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\ExamHasHazard;

/**
 * Factors, regulations, activities and examinations of a medical exam, as submitted in the exam form.
 * The form is pre-filled from the employee's job until the exam has its own saved snapshot (both legacy apps).
 */
class ExamHazards extends \Hubleto\Erp\Core
{
  const HIGHEST_CATEGORY = 4;

  /**
   * The four hazard types with all catalogue hazards and the level for this exam:
   * ['isFromJob' => bool, 'hazardTypes' => [['id', 'name', 'uses_categories', 'is_exam_scope', 'hazards' => [['id', 'name', 'level']]]]].
   */
  public function getForForm(int $idExam, int $idEmployee): array
  {
    $levels = $idExam > 0 ? $this->loadSnapshotLevels($idExam) : [];
    $isFromJob = count($levels) == 0;
    if ($isFromJob) $levels = $this->loadJobLevels($idEmployee);

    /** @var Hazard */
    $mHazard = $this->getModel(Hazard::class);

    $hazardTypes = [];
    foreach ($mHazard->getTypeNames() as $idHazardType => $hazardTypeName) {
      $hazards = [];
      $hazardsOfType = $mHazard->record->where('type', $idHazardType)->orderBy('name')->get();
      foreach ($hazardsOfType as $hazard) {
        $hazards[] = ['id' => (int) $hazard->id, 'name' => $hazard->name, 'level' => $levels[(int) $hazard->id] ?? 0];
      }

      $hazardTypes[] = [
        'id' => $idHazardType,
        'name' => $hazardTypeName,
        'uses_categories' => Hazard::typeUsesCategories($idHazardType),
        'is_exam_scope' => $idHazardType == Hazard::TYPE_EXAMINATION,
        'hazards' => $hazards,
      ];
    }

    return ['isFromJob' => $isFromJob, 'hazardTypes' => $hazardTypes];
  }

  /**
   * Replaces the exam's snapshot with [idHazard => level]. Level 0 means "not present" and is not stored.
   */
  public function saveSnapshot(int $idExam, array $levelsByHazard): void
  {
    /** @var ExamHasHazard */
    $mExamHazard = $this->getModel(ExamHasHazard::class);
    $mExamHazard->record->where('id_exam', $idExam)->delete();

    foreach ($levelsByHazard as $idHazard => $level) {
      $level = (int) $level;
      if ($level <= 0) continue;
      if ($level > self::HIGHEST_CATEGORY) $level = self::HIGHEST_CATEGORY;

      $mExamHazard->record->create(['id_exam' => $idExam, 'id_hazard' => (int) $idHazard, 'level' => $level]);
    }
  }

  // [idHazard => level] saved with the exam.
  public function loadSnapshotLevels(int $idExam): array
  {
    $levels = [];
    foreach ($this->getModel(ExamHasHazard::class)->record->where('id_exam', $idExam)->get() as $examHazard) {
      $levels[(int) $examHazard->id_hazard] = (int) $examHazard->level;
    }
    return $levels;
  }

  // [idHazard => level] of the employee's current job (job hazard levels at the branch).
  public function loadJobLevels(int $idEmployee): array
  {
    $employee = $this->getModel(Employee::class)->record->where('id', $idEmployee)->first();
    if (!$employee || (int) $employee->id_job <= 0) return [];

    $jobHazards = $this->getModel(JobHasHazard::class)->record->where('id_job', $employee->id_job)->get();

    $levels = [];
    foreach ($jobHazards as $jobHazard) {
      $levels[(int) $jobHazard->id_hazard] = (int) $jobHazard->level;
    }
    return $levels;
  }
}
