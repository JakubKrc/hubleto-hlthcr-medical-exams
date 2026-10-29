<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\CalendarAuthor;

/**
 * Medical exams in the Hubleto calendar (legacy colour green), derived from the exams.
 * Legacy: no exams of inactive employees; executed exams (with a result) are grey.
 */
class Calendar extends \Hubleto\App\Community\Calendar\Calendar
{
  const COLOR = '#7fd936';
  const EXECUTED_COLOR = '#8e9091';

  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrmedicalexams-loader';
  public string $translationContextInner = 'Calendar';

  public function getCalendarConfig(): array
  {
    return [
      'position' => 5,
      'color' => self::COLOR,
      'title' => $this->translate('Medical exams'),
      'icon' => 'fas fa-stethoscope',
    ];
  }

  public function loadEvent(int $id): array
  {
    $exam = $this->getModel(Models\Exam::class)->record->prepareReadQuery()->where('hlthcr_exams.id', $id)->first();
    if (!$exam) return [];
    return $exam->toArray();
  }

  public function loadEvents(string $dateStart, string $dateEnd, array $filter = []): array
  {
    $query = $this->getModel(Models\Exam::class)->record->prepareReadQuery()
      ->with(['EMPLOYEE.BRANCH'])
      ->where('hlthcr_exams.date_exam', '>=', $dateStart)
      ->where('hlthcr_exams.date_exam', '<=', $dateEnd)
      ->whereIn('hlthcr_exams.id_employee', function ($activeEmployees) {
        $activeEmployees->select('id')->from('hlthcr_employees')->where('is_active', 1);
      })
    ;

    $idAuthor = $this->getService(CalendarAuthor::class)->getAuthorIdToShow($filter);
    if ($idAuthor > 0) {
      $query = $query->where('hlthcr_exams.id_author', $idAuthor);
    }

    // Hubleto's calendar filter "not done" is 1, "done" is 2; an exam is done when it has a result.
    $completedFilter = (int) ($filter['fCompleted'] ?? 0);
    if ($completedFilter == 1) $query = $query->where('hlthcr_exams.result', Models\Exam::RESULT_UNDETERMINED);
    if ($completedFilter == 2) $query = $query->where('hlthcr_exams.result', '!=', Models\Exam::RESULT_UNDETERMINED);

    $events = [];
    foreach ($query->get()->toArray() as $exam) {
      $events[] = $this->convertToCalendarEvent($exam);
    }
    return $events;
  }

  private function convertToCalendarEvent(array $exam): array
  {
    $hasTime = !empty($exam['time_exam']);
    $isExecuted = (int) $exam['result'] != Models\Exam::RESULT_UNDETERMINED;
    $title = $this->translate('Medical exam - {{ employee }} - {{ branch }}', [
      'employee' => $exam['EMPLOYEE']['name'] ?? '',
      'branch' => $exam['EMPLOYEE']['BRANCH']['name'] ?? '',
    ]);

    return [
      'id' => (int) $exam['id'],
      'start' => $hasTime ? $exam['date_exam'] . ' ' . $exam['time_exam'] : $exam['date_exam'],
      'allDay' => !$hasTime,
      'title' => $title,
      'color' => $isExecuted ? self::EXECUTED_COLOR : self::COLOR,
      'source' => 'hlthcr-medical-exams',
      'id_owner' => $exam['id_author'],
      'url' => 'hlthcr-medical-exams/exams/' . $exam['id'],
    ];
  }
}
