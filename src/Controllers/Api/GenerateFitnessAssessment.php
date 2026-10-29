<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers\Api;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\Community\Documents\Generator;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\FitnessAssessment;

/**
 * Generates the fitness assessment of an exam as a PDF through Hubleto's document Generator.
 * The PDF is kept with the exam only (not as a Documents app entry, which every user would see); each run replaces it.
 */
class GenerateFitnessAssessment extends \Hubleto\Erp\Controllers\ApiController
{
  const FOLDER = 'hlthcr/fitness-assessments';

  public function renderJson(): array
  {
    $idExam = $this->router()->urlParamAsInteger('idExam');

    /** @var FitnessAssessment */
    $fitnessAssessment = $this->getService(FitnessAssessment::class);
    $vars = $fitnessAssessment->getTemplateVars($idExam);
    if (count($vars) == 0) {
      return ['status' => 'error', 'message' => $this->translate('The medical exam does not exist.')];
    }

    $documentName = $this->translate('Medical fitness assessment') . ' ' . $vars['employee']['name'] . ' ' . $vars['exam']['date'];
    $pdfFile = self::FOLDER . '/' . \Hubleto\Framework\Helper::str2url($documentName) . '-' . date('Ymd-His') . '.pdf';

    $folderPath = $this->env()->uploadFolder . '/' . self::FOLDER;
    if (!is_dir($folderPath)) mkdir($folderPath, 0775, true);

    /** @var Generator */
    $generator = $this->getService(Generator::class);
    $generator->generatePdfDocumentFromTemplate($documentName, Exam::class, $idExam, 0, $pdfFile, $vars, false);

    /** @var Exam */
    $mExam = $this->getModel(Exam::class);
    $previousPdfFile = (string) $mExam->record->where('id', $idExam)->value('fitness_assessment_file');
    $mExam->record->where('id', $idExam)->update(['fitness_assessment_file' => $pdfFile]);
    // Two runs within one second write the same file name, so the "previous" file is the new one.
    if ($previousPdfFile != $pdfFile) {
      $this->getService(ProtectedFile::class)->delete($previousPdfFile);
    }

    return ['status' => 'success', 'file' => $pdfFile];
  }
}
