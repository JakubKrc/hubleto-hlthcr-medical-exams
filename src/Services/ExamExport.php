<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;

/**
 * "Export lekárskych prehliadok" (legacy ExportStaffHealthChecks): exams in a period as CSV,
 * limited to the exams the user sees (the legacy export had no limit).
 */
class ExamExport extends \Hubleto\Erp\Core
{
  // "Any" for the type and result filters; legacy used 0, so "undetermined" results could not be exported alone.
  const ANY = -1;

  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrmedicalexams-loader';
  public string $translationContextInner = 'Services\\ExamExport';

  /**
   * CSV for Excel (";" separated, Windows-1250, as in the legacy app). Dates are 'YYYY-MM-DD', both days included.
   */
  public function buildCsv(string $dateFrom, string $dateTo, int $idBranch, int $examType, int $result): string
  {
    /** @var Exam */
    $mExam = $this->getModel(Exam::class);
    $query = $mExam->record->prepareReadQuery()
      ->with(['EMPLOYEE.BRANCH', 'CLINIC'])
      ->where('hlthcr_exams.date_exam', '>=', $dateFrom)
      ->where('hlthcr_exams.date_exam', '<=', $dateTo)
      ->orderBy('hlthcr_exams.date_exam')
      ->orderBy('hlthcr_exams.time_exam')
    ;
    if ($idBranch > 0) {
      $query = $query->whereIn('hlthcr_exams.id_employee', function ($employeesOfBranch) use ($idBranch) {
        $employeesOfBranch->select('id')->from('hlthcr_employees')->where('id_branch', $idBranch);
      });
    }
    if ($examType != self::ANY) $query = $query->where('hlthcr_exams.exam_type', $examType);
    if ($result != self::ANY) $query = $query->where('hlthcr_exams.result', $result);

    $typeLabels = $mExam->getTypeLabels();
    $resultLabels = $mExam->getResultLabels();

    $lines = [];
    $lines[] = $this->toCsvLine([
      $this->translate('Date and time of exam'), $this->translate('Employee'), $this->translate('Date of birth'),
      $this->translate('Type of exam'), $this->translate('Result'), $this->translate('Date of next exam'),
      $this->translate('Type of next exam'), $this->translate('Clinic'), $this->translate('Branch'),
    ]);
    foreach ($query->get() as $exam) {
      $examDateTime = date('d.m.Y', strtotime($exam->date_exam)) . (empty($exam->time_exam) ? '' : ' ' . substr($exam->time_exam, 0, 5));
      $lines[] = $this->toCsvLine([
        $examDateTime,
        $exam->EMPLOYEE->name ?? '',
        empty($exam->EMPLOYEE->date_of_birth) ? '' : date('d.m.Y', strtotime($exam->EMPLOYEE->date_of_birth)),
        $typeLabels[(int) $exam->exam_type] ?? '',
        $resultLabels[(int) $exam->result] ?? '',
        empty($exam->date_next_exam) ? '' : date('d.m.Y', strtotime($exam->date_next_exam)),
        $typeLabels[(int) $exam->next_exam_type] ?? '',
        $exam->CLINIC->name ?? '',
        $exam->EMPLOYEE->BRANCH->name ?? '',
      ]);
    }

    return iconv('UTF-8', 'Windows-1250//TRANSLIT', implode("\r\n", $lines) . "\r\n");
  }

  private function toCsvLine(array $values): string
  {
    $quotedValues = [];
    foreach ($values as $value) {
      $text = (string) $value;
      // A cell starting like a formula would run as one in a spreadsheet, so it gets a leading apostrophe.
      $looksLikeFormula = $text !== '' && in_array($text[0], ['=', '+', '-', '@']);
      if ($looksLikeFormula) $text = "'" . $text;
      $quotedValues[] = '"' . str_replace('"', '""', $text) . '"';
    }
    return implode(';', $quotedValues);
  }
}
