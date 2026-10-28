<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\EmptyDatesAsNull;
use Hubleto\App\Community\Auth\Models\User;
use Hubleto\App\External\WaiBlue\HlthcrCore\Db\Column\StoredFile;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Clinic;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\KeepsStoredFiles;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\UsesStoredValues;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\BranchHasClinic;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Job;
use Hubleto\Framework\Exceptions\RecordSaveException;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers\Api\ClinicLookup;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamAccess;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamHazards;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\NextExamDate;
use Hubleto\Framework\Db\Column\Boolean;
use Hubleto\Framework\Db\Column\Date;
use Hubleto\Framework\Db\Column\Integer;
use Hubleto\Framework\Db\Column\Lookup;
use Hubleto\Framework\Db\Column\Time;
use Hubleto\Framework\Db\Column\Varchar;

/**
 * Preventive medical exam (LPP) of a client employee. Types and results use the codes both legacy apps share.
 * Its factors, regulations, activities and examinations are a snapshot of the exam form (ExamHasHazard).
 */
class Exam extends \Hubleto\App\External\WaiBlue\HlthcrCore\Models\HlthcrModel
{
  use EmptyDatesAsNull;
  use KeepsStoredFiles;
  use UsesStoredValues;

  const TYPE_ENTRY = 1;
  const TYPE_EXIT = 2;
  const TYPE_PERIODIC = 3;
  const TYPE_BEFORE_WORK_CHANGE = 4;
  const TYPE_EXTRAORDINARY = 5;
  // Only for the type of the next exam.
  const TYPE_UNDETERMINED = 0;

  const RESULT_UNDETERMINED = 0;
  const RESULT_FIT = 1;
  const RESULT_FIT_WITH_RESTRICTIONS = 2;
  const RESULT_UNFIT = 3;
  const RESULT_VACCINATION = 4;

  // Form field with the submitted factors as JSON {idHazard: level}; saved into ExamHasHazard, not a column.
  const SUBMITTED_HAZARDS_FIELD = 'hazard_levels';

  public string $table = 'hlthcr_exams';
  public string $recordManagerClass = RecordManagers\Exam::class;
  public ?string $lookupSqlValue = 'concat(date_format({%TABLE%}.date_exam, "%d.%m.%Y"), " (", {%TABLE%}.id, ")")';
  public ?string $lookupUrlDetail = 'hlthcr-medical-exams/exams/{%ID%}';

  public array $relations = [
    'EMPLOYEE' => [ self::BELONGS_TO, Employee::class, 'id_employee', 'id' ],
    'JOB' => [ self::BELONGS_TO, Job::class, 'id_job', 'id' ],
    'CLINIC' => [ self::BELONGS_TO, Clinic::class, 'id_clinic', 'id' ],
    'AUTHOR' => [ self::BELONGS_TO, User::class, 'id_author', 'id' ],
    'HAZARDS' => [ self::HAS_MANY, ExamHasHazard::class, 'id_exam', 'id' ],
    'PLANNED_EXAM' => [ self::BELONGS_TO, Exam::class, 'id_planned_exam', 'id' ],
  ];

  /** @var array|null Factors submitted with the form, kept between the "before" and "after" save hooks. */
  private ?array $submittedHazardLevels = null;

  /** @var array Files of the exam being deleted; removed after the record is gone. */
  private array $filesOfDeletedExam = [];

  public function getTypeLabels(): array
  {
    return [
      self::TYPE_ENTRY => $this->translate('Entry'),
      self::TYPE_EXIT => $this->translate('Exit'),
      self::TYPE_PERIODIC => $this->translate('Periodic'),
      self::TYPE_BEFORE_WORK_CHANGE => $this->translate('Before a change of work assignment'),
      self::TYPE_EXTRAORDINARY => $this->translate('Extraordinary'),
    ];
  }

  public function getResultLabels(): array
  {
    return [
      self::RESULT_UNDETERMINED => $this->translate('Undetermined'),
      self::RESULT_FIT => $this->translate('Fit'),
      self::RESULT_FIT_WITH_RESTRICTIONS => $this->translate('Fit with restrictions'),
      self::RESULT_UNFIT => $this->translate('Unfit'),
      self::RESULT_VACCINATION => $this->translate('Vaccination'),
    ];
  }

  public function describeColumns(): array
  {
    $nextTypeLabels = [self::TYPE_UNDETERMINED => $this->translate('Undetermined')] + $this->getTypeLabels();

    return array_merge(parent::describeColumns(), [
      'id_employee' => (new Lookup($this, $this->translate('Employee'), Employee::class, 'RESTRICT'))
        ->setRequired()
        ->setDefaultVisible()
      ,
      // The employee's job at the time of the exam (one legacy app stores it); its hazards decide the next exam.
      'id_job' => (new Lookup($this, $this->translate('Job position'), Job::class, 'RESTRICT'))->setReadonly(),
      'exam_type' => (new Integer($this, $this->translate('Type of medical exam')))
        ->setRequired()
        ->setDefaultVisible()
        ->setDefaultValue(self::TYPE_ENTRY)
        ->setEnumValues($this->getTypeLabels())
      ,
      'date_exam' => (new Date($this, $this->translate('Date of medical exam')))->setRequired()->setDefaultVisible(),
      'time_exam' => (new Time($this, $this->translate('Time of medical exam')))->setDefaultVisible(),
      'next_exam_type' => (new Integer($this, $this->translate('Type of next medical exam')))
        ->setDefaultValue(self::TYPE_UNDETERMINED)
        ->setEnumValues($nextTypeLabels)
      ,
      'date_next_exam' => (new Date($this, $this->translate('Date of next medical exam')))
        ->setDefaultVisible()
        ->setHint($this->translate('Filled in when a result is recorded, from the job interval or the hazard intervals; the next exam is then planned.'))
      ,
      // Set when the user typed the next exam date, so it is not recomputed.
      'next_exam_date_typed' => (new Boolean($this, $this->translate('Next exam date typed'))),
      // The exam this one planned; it moves with the next exam date and is removed after "unfit" or an exit exam.
      'id_planned_exam' => (new Lookup($this, $this->translate('Planned next medical exam'), Exam::class, 'SET NULL'))->setReadonly(),
      'result' => (new Integer($this, $this->translate('Result of medical exam')))
        ->setDefaultVisible()
        ->setDefaultValue(self::RESULT_UNDETERMINED)
        ->setEnumValues($this->getResultLabels())
        ->setEnumCssClasses([
          self::RESULT_UNDETERMINED => 'bg-yellow-100 text-yellow-800',
          self::RESULT_FIT => 'bg-green-100 text-green-800',
          self::RESULT_FIT_WITH_RESTRICTIONS => 'bg-orange-100 text-orange-800',
          self::RESULT_UNFIT => 'bg-red-100 text-red-800',
          self::RESULT_VACCINATION => 'bg-blue-100 text-blue-800',
        ])
      ,
      'note' => (new Varchar($this, $this->translate('Note'))),
      'id_clinic' => (new Lookup($this, $this->translate('Clinic'), Clinic::class))
        ->setRequired()
        ->setDefaultVisible()
        // Only clinics of the employee's branch; medical staff only their own clinic.
        ->setEndpoint(ClinicLookup::ENDPOINT)
      ,
      'document' => (new StoredFile($this, $this->translate('Document')))
        ->setDownloadUrl('hlthcr-medical-exams/exams/{%ID%}/document')
        ->setFolderPath('hlthcr/exam-documents')
        ->setRenamePattern('{%FILENAME_ASCII%}__{%Y%}{%M%}{%D%}_{%H%}{%I%}{%S%}.{%EXT%}')
      ,
      'scan' => (new StoredFile($this, $this->translate('Scan')))
        ->setDownloadUrl('hlthcr-medical-exams/exams/{%ID%}/scan')
        ->setFolderPath('hlthcr/exam-scans')
        ->setRenamePattern('{%FILENAME_ASCII%}__{%Y%}{%M%}{%D%}_{%H%}{%I%}{%S%}.{%EXT%}')
      ,
      'id_author' => (new Lookup($this, $this->translate('Created by'), User::class))->setReadonly(),
      // Path in the upload folder of the newest fitness assessment PDF (served only through a checked download).
      'fitness_assessment_file' => (new Varchar($this, $this->translate('Fitness assessment (PDF)')))->setReadonly(),
    ]);
  }

  public function describeTable(): \Hubleto\Framework\Description\Table
  {
    $description = parent::describeTable();
    $description->ui['addButtonText'] = $this->translate('Add medical exam');
    $description->ui['orderBy'] = ['field' => 'date_exam', 'direction' => 'asc'];
    $description->show(['header', 'fulltextSearch', 'columnSearch', 'moreActionsButton']);
    $description->hide(['footer']);

    $description->addFilter(RecordManagers\Exam::PERIOD_FILTER, [
      'title' => $this->translate('Period'),
      'options' => [
        RecordManagers\Exam::PERIOD_FILTER_ALL => $this->translate('All'),
        RecordManagers\Exam::PERIOD_FILTER_UPCOMING => $this->translate('Upcoming'),
        RecordManagers\Exam::PERIOD_FILTER_PAST => $this->translate('Past'),
      ],
    ]);

    return $description;
  }

  public function describeForm(): \Hubleto\Framework\Description\Form
  {
    $description = parent::describeForm();
    $description->defaultValues['id_author'] = $this->authProvider()->getUserId();

    /** @var ExamAccess */
    $examAccess = $this->getService(ExamAccess::class);
    if ($examAccess->seesOnlyOwnClinicExams()) {
      $description->defaultValues['id_clinic'] = $examAccess->getOwnClinicId();
    }

    return $description;
  }

  public function getRelationsIncludedInLoadTableData(): array|null
  {
    return ['EMPLOYEE', 'CLINIC'];
  }

  public function getMaxReadLevelForLoadTableData(): int
  {
    return 1;
  }

  public function onBeforeCreate(array $record): array
  {
    $record = parent::onBeforeCreate($record);
    $record = $this->emptyDatesAsNull($record);
    $this->submittedHazardLevels = $this->readSubmittedHazardLevels($record);

    $record['id_author'] = $this->getAuthorId();
    $record['id_job'] = $this->getJobOfNewExam($record);
    $record['result'] = (int) ($record['result'] ?? self::RESULT_UNDETERMINED);
    $record['next_exam_date_typed'] = (string) ($record['date_next_exam'] ?? '') != '';
    $record['id_planned_exam'] = null;

    // Medical staff plan exams only at their own clinics (the first one unless another own one is chosen).
    /** @var ExamAccess */
    $examAccess = $this->getService(ExamAccess::class);
    $isOwnClinic = in_array((int) ($record['id_clinic'] ?? 0), $examAccess->getOwnClinicIds());
    if ($examAccess->seesOnlyOwnClinicExams() && !$isOwnClinic) {
      $record['id_clinic'] = $examAccess->getOwnClinicId();
    }
    $this->refuseClinicNotServingBranch($record);

    $record = $this->keepStoredFile($record, 'document', null);
    return $this->keepStoredFile($record, 'scan', null);
  }

  // Employee, job, type, clinic and author stay as created.
  public function onBeforeUpdate(array $record): array
  {
    $record = parent::onBeforeUpdate($record);
    $record = $this->emptyDatesAsNull($record);
    $this->submittedHazardLevels = $this->readSubmittedHazardLevels($record);
    // The next exam rules read these; a save that leaves one out must not clear the next exam.
    $record = $this->addStoredValuesOfMissingColumns($record, ['result', 'date_exam', 'date_next_exam', 'next_exam_type']);

    $originalExam = $this->record->where('id', (int) $record['id'])->first();
    if ($originalExam) {
      $record['id_employee'] = $originalExam->id_employee;
      $record['id_job'] = $originalExam->id_job;
      $record['exam_type'] = $originalExam->exam_type;
      $record['id_clinic'] = $originalExam->id_clinic;
      $record['id_author'] = $originalExam->id_author;
      $record['id_planned_exam'] = $originalExam->id_planned_exam;
      $record['next_exam_date_typed'] = $this->isNextExamDateTyped($record, $originalExam);
    }

    $record = $this->keepStoredFile($record, 'document', $originalExam);
    return $this->keepStoredFile($record, 'scan', $originalExam);
  }

  public function onAfterCreate(array $savedRecord): array
  {
    $savedRecord = parent::onAfterCreate($savedRecord);
    $this->saveSubmittedHazards((int) $savedRecord['id']);

    return $this->updateNextExam($savedRecord);
  }

  public function onAfterUpdate(array $originalRecord, array $savedRecord): array
  {
    $savedRecord = parent::onAfterUpdate($originalRecord, $savedRecord);
    $this->saveSubmittedHazards((int) $savedRecord['id']);

    return $this->updateNextExam($savedRecord);
  }

  public function onBeforeDelete(int $id): int
  {
    $id = parent::onBeforeDelete($id);
    $exam = $this->record->where('id', $id)->first();
    $this->filesOfDeletedExam = $exam ? [(string) $exam->document, (string) $exam->scan, (string) $exam->fitness_assessment_file] : [];
    return $id;
  }

  // The scan and the document are deleted with the exam.
  public function onAfterDelete(int $id): int
  {
    $id = parent::onAfterDelete($id);
    foreach ($this->filesOfDeletedExam as $file) {
      if ($file == '') continue;
      $filePath = $this->config()->getAsString('uploadFolder') . '/' . $file;
      if (is_file($filePath)) unlink($filePath);
    }
    $this->filesOfDeletedExam = [];
    return $id;
  }

  // Used by the Documents Generator when no template of the Documents app is chosen.
  public function getDocumentDefaultTemplate(array $vars = []): string
  {
    /** @var \Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\FitnessAssessment */
    $fitnessAssessment = $this->getService(\Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\FitnessAssessment::class);
    return $fitnessAssessment->getDefaultTemplate();
  }

  // The chosen job must be one of the employee's branch; without one, the employee's current job.
  private function getJobOfNewExam(array $record): ?int
  {
    $employee = $this->getModel(Employee::class)->record->where('id', (int) ($record['id_employee'] ?? 0))->first();
    if (!$employee) return null;

    $idChosenJob = (int) ($record['id_job'] ?? 0);
    if ($idChosenJob <= 0) {
      $idEmployeeJob = (int) $employee->id_job;
      if ($idEmployeeJob <= 0) return null;
      return $idEmployeeJob;
    }

    $chosenJob = $this->getModel(Job::class)->record->where('id', $idChosenJob)->first();
    $isJobOfBranch = $chosenJob && (int) $chosenJob->id_branch == (int) $employee->id_branch;
    if (!$isJobOfBranch) {
      throw new RecordSaveException($this->translate('The job position is not one of the employee\'s branch.'));
    }
    return $idChosenJob;
  }

  // The signed-in user; none when the exam is created on the command line (demo data).
  private function getAuthorId(): ?int
  {
    $idUser = (int) $this->authProvider()->getUserId();
    if ($idUser <= 0) return null;
    return $idUser;
  }

  // Both legacy apps offer only the clinics that serve the employee's branch; the server checks a new exam too.
  private function refuseClinicNotServingBranch(array $record): void
  {
    $idClinic = (int) ($record['id_clinic'] ?? 0);
    $employee = $this->getModel(Employee::class)->record->where('id', (int) ($record['id_employee'] ?? 0))->first();
    if ($idClinic <= 0 || !$employee) return;

    $clinicServesBranch = $this->getModel(BranchHasClinic::class)->record
      ->where('id_branch', (int) $employee->id_branch)
      ->where('id_clinic', $idClinic)
      ->exists()
    ;
    if (!$clinicServesBranch) {
      throw new RecordSaveException($this->translate('The clinic does not serve the employee\'s branch.'));
    }
  }

  // The date is typed when this save changes it to a value; emptying it lets it be computed again.
  private function isNextExamDateTyped(array $record, mixed $originalExam): bool
  {
    if (!array_key_exists('date_next_exam', $record)) return (bool) $originalExam->next_exam_date_typed;

    $submittedDate = (string) ($record['date_next_exam'] ?? '');
    $originalDate = (string) ($originalExam->date_next_exam ?? '');
    if ($submittedDate == $originalDate) return (bool) $originalExam->next_exam_date_typed;
    return $submittedDate != '';
  }

  /**
   * Plan on result: the next exam date is computed when a fit result is recorded, unless it was typed,
   * and recomputed when the exam changes. Exit exams and "unfit" have no next exam. The planned exam follows the date.
   */
  private function updateNextExam(array $savedRecord): array
  {
    /** @var NextExamDate */
    $nextExamDate = $this->getService(NextExamDate::class);

    $dateNextExam = '';
    $isExcluded = $nextExamDate->isNextExamExcluded($savedRecord);
    $isTyped = (bool) ($savedRecord['next_exam_date_typed'] ?? false);
    if ($isExcluded) {
      $dateNextExam = '';
    } elseif ($isTyped) {
      $dateNextExam = (string) ($savedRecord['date_next_exam'] ?? '');
    } else {
      $dateNextExam = $nextExamDate->computeForExam($savedRecord);
    }

    $storedDate = (string) ($savedRecord['date_next_exam'] ?? '');
    if ($dateNextExam != $storedDate) {
      $this->record->where('id', (int) $savedRecord['id'])->update(['date_next_exam' => $dateNextExam == '' ? null : $dateNextExam]);
      $savedRecord['date_next_exam'] = $dateNextExam == '' ? null : $dateNextExam;
    }

    $mayPlan = $dateNextExam != '' && $nextExamDate->hasResultWithNextExam($savedRecord);
    if ($mayPlan) {
      return $this->planNextExam($savedRecord, $dateNextExam);
    }
    return $this->removeOpenPlannedExam($savedRecord);
  }

  /**
   * Keeps one planned exam on the next exam date: moves the one planned before, or creates it.
   * Created directly, so the planned exam does not plan further exams before it has a result.
   */
  private function planNextExam(array $savedRecord, string $dateNextExam): array
  {
    $idExam = (int) $savedRecord['id'];
    $plannedExam = $this->findPlannedExam($savedRecord);

    if ($plannedExam) {
      $isStillOpen = (int) $plannedExam->result == self::RESULT_UNDETERMINED;
      if ($isStillOpen && (string) $plannedExam->date_exam != $dateNextExam) {
        $plannedExam->update(['date_exam' => $dateNextExam]);
      }
      return $savedRecord;
    }

    $nextExamType = (int) ($savedRecord['next_exam_type'] ?? self::TYPE_UNDETERMINED);
    if ($nextExamType == self::TYPE_UNDETERMINED) $nextExamType = self::TYPE_PERIODIC;

    // An exam the user already entered on that date is enough; it is not linked, so it is never removed here.
    $examAlreadyOnDate = $this->record
      ->where('id_employee', (int) $savedRecord['id_employee'])
      ->where('date_exam', $dateNextExam)
      ->where('id', '!=', $idExam)
      ->exists()
    ;
    if ($examAlreadyOnDate) return $savedRecord;

    $idPlannedExam = (int) $this->record->create([
      'id_employee' => $savedRecord['id_employee'],
      'exam_type' => $nextExamType,
      'date_exam' => $dateNextExam,
      'result' => self::RESULT_UNDETERMINED,
      'id_clinic' => $savedRecord['id_clinic'],
      'id_job' => $this->getJobOfNewExam(['id_employee' => $savedRecord['id_employee']]),
      'id_author' => $this->getAuthorId(),
    ])->id;

    $this->record->where('id', $idExam)->update(['id_planned_exam' => $idPlannedExam, 'next_exam_type' => $nextExamType]);
    $savedRecord['id_planned_exam'] = $idPlannedExam;
    $savedRecord['next_exam_type'] = $nextExamType;
    return $savedRecord;
  }

  // A planned exam that has no result and no files yet is removed when no next exam follows any more.
  private function removeOpenPlannedExam(array $savedRecord): array
  {
    $plannedExam = $this->findPlannedExam($savedRecord);
    if (!$plannedExam) return $savedRecord;

    $isStillOpen = (int) $plannedExam->result == self::RESULT_UNDETERMINED;
    $hasFiles = (string) $plannedExam->document != '' || (string) $plannedExam->scan != '';
    if ($isStillOpen && !$hasFiles) {
      $plannedExam->delete();
    }

    $this->record->where('id', (int) $savedRecord['id'])->update(['id_planned_exam' => null]);
    $savedRecord['id_planned_exam'] = null;
    return $savedRecord;
  }

  private function findPlannedExam(array $savedRecord): mixed
  {
    $idPlannedExam = (int) ($savedRecord['id_planned_exam'] ?? 0);
    if ($idPlannedExam <= 0) return null;
    return $this->record->where('id', $idPlannedExam)->first();
  }

  // Null when the form did not send factors (then the saved snapshot stays as it is).
  private function readSubmittedHazardLevels(array $record): ?array
  {
    if (!array_key_exists(self::SUBMITTED_HAZARDS_FIELD, $record)) return null;

    $submitted = $record[self::SUBMITTED_HAZARDS_FIELD];
    if (is_string($submitted)) $submitted = json_decode($submitted, true);
    return is_array($submitted) ? $submitted : null;
  }

  private function saveSubmittedHazards(int $idExam): void
  {
    if ($this->submittedHazardLevels === null) return;
    $this->getService(ExamHazards::class)->saveSnapshot($idExam, $this->submittedHazardLevels);
    $this->submittedHazardLevels = null;
  }
}
