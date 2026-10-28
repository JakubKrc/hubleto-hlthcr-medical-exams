<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers\Api;

use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamHazards;

/**
 * Factors, hazards and examinations for the exam form: the exam's snapshot, or the employee's job levels.
 */
class GetExamHazards extends \Hubleto\Erp\Controllers\ApiController
{
  public function renderJson(): array
  {
    $idExam = $this->router()->urlParamAsInteger('idExam');
    $idEmployee = $this->router()->urlParamAsInteger('idEmployee');

    // Only exams and employees the user may see.
    if ($idExam > 0) {
      $exam = $this->getModel(Exam::class)->record->prepareReadQuery()->where('hlthcr_exams.id', $idExam)->first();
      if (!$exam) return ['status' => 'error', 'message' => $this->translate('The medical exam does not exist.')];
      $idEmployee = (int) $exam->id_employee;
    }
    $employeeIsVisible = $idEmployee <= 0
      || $this->getModel(Employee::class)->record->prepareReadQuery()->where('hlthcr_employees.id', $idEmployee)->exists();
    if (!$employeeIsVisible) $idEmployee = 0;

    return array_merge(['status' => 'success'], $this->getService(ExamHazards::class)->getForForm($idExam, $idEmployee));
  }
}
