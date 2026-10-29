<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\Community\Notifications\Models\Notification;
use Hubleto\App\Community\Notifications\Sender;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\Recipients;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;

/**
 * Tells the nurses and administrators about a new exam or a new exam date, as one legacy app does.
 * Only in the app (Hubleto's notifications), no e-mail: the legacy app had its e-mail turned off too.
 */
class ExamNotifications extends \Hubleto\Erp\Core
{
  const NOTIFICATIONS_APP = 'Hubleto\\App\\Community\\Notifications';
  const CATEGORY_MEDICAL_EXAMS = 2100;

  public function registerCategory(): void
  {
    if (!class_exists(Notification::class)) return;
    Notification::addCategory(self::CATEGORY_MEDICAL_EXAMS, 'Medical exams');
  }

  public function notifyExamPlanners(array $exam): void
  {
    if (!$this->appManager()->isAppInstalled(self::NOTIFICATIONS_APP)) return;

    $employee = $this->getModel(Employee::class)->record->where('id', (int) ($exam['id_employee'] ?? 0))->first();
    if (!$employee) return;
    $branch = $this->getModel(Branch::class)->record->where('id', (int) $employee->id_branch)->first();

    $employeeText = (string) $employee->name;
    if ((string) $employee->date_of_birth != '') {
      $employeeText .= ' (' . date('d.m.Y', strtotime((string) $employee->date_of_birth)) . ')';
    }
    if ($branch) $employeeText .= ' - ' . $branch->name;
    $examDateText = date('d.m.Y', strtotime((string) ($exam['date_exam'] ?? '')));

    /** @var Recipients */
    $recipients = $this->getService(Recipients::class);
    /** @var Sender */
    $sender = $this->getService(Sender::class);

    // The sender skips the user who saved the exam.
    foreach ($recipients->getExamPlanners() as $idUser) {
      $sender->send(
        self::CATEGORY_MEDICAL_EXAMS,
        ['hlthcr-medical-exams', 'planned-exam'],
        Exam::class,
        (int) $exam['id'],
        $idUser,
        $this->translate('Planned medical exam'),
        $employeeText . ', ' . $examDateText,
        'hlthcr-medical-exams/exams/' . (int) $exam['id']
      );
    }
  }
}
