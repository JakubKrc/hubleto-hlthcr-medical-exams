<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Job;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Hazard;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\MonthDates;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;

/**
 * Date of the next periodic exam. The job's own interval wins (one legacy app); otherwise the shortest
 * interval of the exam's and the job's hazards at their category (the other legacy app). No interval means no next exam.
 */
class NextExamDate extends \Hubleto\Erp\Core
{
  // A next exam follows only a recorded result like these (as in one legacy app; planning after "unfit" makes no sense).
  const RESULTS_WITH_NEXT_EXAM = [Exam::RESULT_FIT, Exam::RESULT_FIT_WITH_RESTRICTIONS, Exam::RESULT_VACCINATION];

  // After an exit exam or an "unfit" result no next exam follows, not even a typed date.
  public function isNextExamExcluded(array $exam): bool
  {
    $isExitExam = (int) ($exam['exam_type'] ?? 0) == Exam::TYPE_EXIT;
    $isUnfit = (int) ($exam['result'] ?? 0) == Exam::RESULT_UNFIT;
    return $isExitExam || $isUnfit;
  }

  public function hasResultWithNextExam(array $exam): bool
  {
    return in_array((int) ($exam['result'] ?? 0), self::RESULTS_WITH_NEXT_EXAM);
  }

  /**
   * Returns 'YYYY-MM-DD', or '' when no next exam follows yet (no result, exit, unfit, or no interval known).
   */
  public function computeForExam(array $exam): string
  {
    $dateExam = (string) ($exam['date_exam'] ?? '');
    if ($dateExam == '') return '';
    if ($this->isNextExamExcluded($exam)) return '';
    if (!$this->hasResultWithNextExam($exam)) return '';

    $intervalMonths = $this->getIntervalMonths((int) ($exam['id'] ?? 0), (int) ($exam['id_employee'] ?? 0));
    if ($intervalMonths <= 0) return '';

    return MonthDates::addMonths($dateExam, $intervalMonths);
  }

  public function getIntervalMonths(int $idExam, int $idEmployee): int
  {
    /** @var Employee */
    $mEmployee = $this->getModel(Employee::class);
    $employee = $mEmployee->record->where('id', $idEmployee)->first();
    if (!$employee) return 0;

    /** @var Job */
    $mJob = $this->getModel(Job::class);
    $job = $mJob->record->where('id', (int) $employee->id_job)->first();
    $jobInterval = $job ? (int) $job->exam_interval_months : 0;
    if ($jobInterval > 0) return $jobInterval;

    return $this->getShortestHazardInterval($idExam, $idEmployee);
  }

  // Shortest positive interval over the exam's hazards and the job's hazards, each at its category.
  private function getShortestHazardInterval(int $idExam, int $idEmployee): int
  {
    /** @var ExamHazards */
    $examHazards = $this->getService(ExamHazards::class);

    $levelsByHazard = $examHazards->loadJobLevels($idEmployee);
    if ($idExam > 0) {
      foreach ($examHazards->loadSnapshotLevels($idExam) as $idHazard => $level) {
        $levelsByHazard[$idHazard] = $level;
      }
    }
    if (count($levelsByHazard) == 0) return 0;

    /** @var Hazard */
    $mHazard = $this->getModel(Hazard::class);
    $hazards = $mHazard->record->whereIn('id', array_keys($levelsByHazard))->get()->toArray();

    $shortestInterval = 0;
    foreach ($hazards as $hazard) {
      // A factor uses the interval of its category; the yes/no types use the interval without category.
      $category = 0;
      if (Hazard::typeUsesCategories((int) $hazard['type'])) {
        $category = (int) $levelsByHazard[(int) $hazard['id']];
      }

      $interval = Hazard::intervalMonthsForCategory($hazard, $category);
      if ($interval <= 0) continue;
      if ($shortestInterval == 0 || $interval < $shortestInterval) {
        $shortestInterval = $interval;
      }
    }
    return $shortestInterval;
  }
}
