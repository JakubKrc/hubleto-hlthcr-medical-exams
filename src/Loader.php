<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams;

use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Clinic;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\DemoData;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;

class Loader extends \Hubleto\App\External\WaiBlue\HlthcrCore\HlthcrApp
{
  public function init(): void
  {
    \Hubleto\App\External\WaiBlue\HlthcrCore\Services\Translator::register();
    parent::init();

    $this->router()->get([
      '/^hlthcr-medical-exams\/?$/' => Controllers\Exams::class,
      '/^hlthcr-medical-exams\/settings\/?$/' => Controllers\Settings::class,
      '/^hlthcr-medical-exams\/exams\/(?<idExam>\d+)\/(?<file>document|scan)\/?$/' => Controllers\DownloadExamFile::class,
      '/^hlthcr-medical-exams\/exams\/(?<idExam>\d+)\/fitness-assessment\/?$/' => Controllers\FitnessAssessmentPreview::class,
      '/^hlthcr-medical-exams\/exams\/(?<idExam>\d+)\/fitness-assessment\/download\/?$/' => Controllers\DownloadFitnessAssessment::class,
      '/^hlthcr-medical-exams\/api\/generate-fitness-assessment\/?$/' => Controllers\Api\GenerateFitnessAssessment::class,
      '/^hlthcr-medical-exams\/api\/get-exam-hazards\/?$/' => Controllers\Api\GetExamHazards::class,
      '/^hlthcr-medical-exams\/api\/clinic-lookup\/?$/' => Controllers\Api\ClinicLookup::class,
      '/^hlthcr-medical-exams\/export\/?$/' => Controllers\ExamExport::class,
      '/^hlthcr-medical-exams\/export\/download\/?$/' => Controllers\DownloadExamExport::class,
    ]);
    $this->router()->crud('hlthcr-medical-exams/exams', Controllers\Exams::class);
    $this->router()->crud('hlthcr-medical-exams/exam-has-hazards', Controllers\ExamHasHazards::class);

    $this->addSearchSwitch('l', 'exams');

    /** @var \Hubleto\App\Community\Calendar\Manager $calendarManager */
    $calendarManager = $this->getService(\Hubleto\App\Community\Calendar\Manager::class);
    $calendarManager->addCalendar($this, 'hlthcr-medical-exams', Calendar::class);
  }

  public function installApp(int $round): void
  {
    \Hubleto\App\External\WaiBlue\HlthcrCore\Services\Translator::register();

    if ($round == 1) {
      $this->getModel(Models\Exam::class)->upgradeSchema();
      $this->getModel(Models\ExamHasHazard::class)->upgradeSchema();
      // "php hubleto app install" runs only round 1, so foreign keys are created here too (Hubleto skips them later).
      $this->installForeignKeys();
    }
  }

  public function search(array $expressions): array
  {
    $examsQuery = $this->getModel(Models\Exam::class)->record->prepareReadQuery()->with(['EMPLOYEE']);
    foreach ($expressions as $expression) {
      $examsQuery = $examsQuery->whereIn('hlthcr_exams.id_employee', function ($matchingEmployees) use ($expression) {
        $matchingEmployees->select('id')->from('hlthcr_employees')->where('name', 'like', '%' . $expression . '%');
      });
    }

    $results = [];
    foreach ($examsQuery->orderBy('date_exam', 'desc')->limit(20)->get()->toArray() as $exam) {
      $results[] = [
        'id' => $exam['id'],
        'label' => $this->translate('Medical exam') . ' ' . date('d.m.Y', strtotime($exam['date_exam'])),
        'url' => 'hlthcr-medical-exams/exams/' . $exam['id'],
        'description' => $exam['EMPLOYEE']['name'] ?? '',
      ];
    }
    return $results;
  }

  public function renderSecondSidebar(): string
  {
    return '
      ' . $this->secondSidebarTitle() . '
      <div class="app-sidebar-buttons">
        ' . $this->secondSidebarButton('hlthcr-medical-exams/exams', 'fas fa-stethoscope', $this->translate('Medical exams')) . '
        ' . $this->secondSidebarButton('hlthcr-medical-exams/export', 'fas fa-file-csv', $this->translate('Export of medical exams')) . '
        ' . $this->secondSidebarButton('calendar', 'fas fa-calendar-days', $this->translate('Calendar')) . '
        ' . $this->secondSidebarButton('hlthcr-medical-exams/settings', 'fas fa-cog', $this->translate('Settings')) . '
      </div>
    ';
  }

  public function renderAlerts(): string
  {
    return '';
  }

  // No number in the menu: neither legacy app shows one.
  public function getSidebarBadgeNumber(): int
  {
    return 0;
  }

  /**
   * Past exams of the demo employees of each demo branch: fit with a planned next exam, unfit, and one overdue.
   * Through the model (as a user would save it), so the next exam date and the snapshot follow the real rules.
   */
  public function generateDemoData(): void
  {
    /** @var Models\Exam */
    $mExam = $this->getModel(Models\Exam::class);
    $demoCompanyIds = $this->getService(DemoData::class)->getDemoCompanyIds();
    $demoCustomerIds = $this->getModel(\Hubleto\App\Community\Customers\Models\Customer::class)->record
      ->whereIn('company_id', $demoCompanyIds)
      ->pluck('id')
    ;

    foreach ($demoCustomerIds as $idDemoCustomer) {
      $branch = $this->getModel(Branch::class)->record->where('id_customer', $idDemoCustomer)->orderBy('id')->first();
      if (!$branch) continue;

      $clinicOfBranch = $this->getModel(Clinic::class)->record
        ->whereIn('id', function ($clinicsOfBranch) use ($branch) {
          $clinicsOfBranch->select('id_clinic')->from('hlthcr_branch_has_clinics')->where('id_branch', $branch->id);
        })
        ->first()
      ;
      if (!$clinicOfBranch) continue;

      $demoEmployees = $this->getModel(Employee::class)->record
        ->where('id_branch', $branch->id)
        ->where('is_active', 1)
        ->orderBy('id')
        ->get()
      ;
      $demoExamsByEmployeeIndex = $this->getDemoExamsByEmployeeIndex();
      foreach ($demoEmployees as $employeeIndex => $employee) {
        if (!isset($demoExamsByEmployeeIndex[$employeeIndex])) continue;

        $employeeHasExams = $mExam->record->where('id_employee', $employee->id)->exists();
        if ($employeeHasExams) continue;

        $demoExam = $demoExamsByEmployeeIndex[$employeeIndex];
        $hazardLevels = $this->getService(Services\ExamHazards::class)->getForForm(0, (int) $employee->id);
        $mExam->record->recordCreate([
          'id_employee' => $employee->id,
          'exam_type' => $demoExam['exam_type'],
          'date_exam' => $demoExam['date_exam'],
          'time_exam' => '08:30:00',
          'result' => $demoExam['result'],
          'note' => $demoExam['note'],
          'id_clinic' => $clinicOfBranch->id,
          Models\Exam::SUBMITTED_HAZARDS_FIELD => $this->toLevelsByHazard($hazardLevels['hazardTypes']),
        ]);
      }
    }
  }

  // [idHazard => level] from the hazard types of ExamHazards::getForForm().
  // The third employee (index 2) gets no exam, so the e2e tests can add the first one.
  // The fourth one is a warehouse worker (24 months), so the exam 30 months ago leaves an overdue planned exam.
  private function getDemoExamsByEmployeeIndex(): array
  {
    return [
      0 => [
        'exam_type' => Models\Exam::TYPE_PERIODIC,
        'date_exam' => date('Y-m-d', strtotime('-11 months')),
        'result' => Models\Exam::RESULT_FIT,
        'note' => '',
      ],
      1 => [
        'exam_type' => Models\Exam::TYPE_EXTRAORDINARY,
        'date_exam' => date('Y-m-d', strtotime('-2 months')),
        'result' => Models\Exam::RESULT_UNFIT,
        'note' => 'Nespôsobilá na prácu s chemickými faktormi.',
      ],
      3 => [
        'exam_type' => Models\Exam::TYPE_PERIODIC,
        'date_exam' => date('Y-m-d', strtotime('-30 months')),
        'result' => Models\Exam::RESULT_FIT,
        'note' => '',
      ],
    ];
  }

  private function toLevelsByHazard(array $hazardTypes): array
  {
    $levelsByHazard = [];
    foreach ($hazardTypes as $hazardType) {
      foreach ($hazardType['hazards'] as $hazard) $levelsByHazard[$hazard['id']] = $hazard['level'];
    }
    return $levelsByHazard;
  }
}
