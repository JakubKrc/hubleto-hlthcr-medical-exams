<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

use Hubleto\App\Community\Documents\Generator;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\FitnessAssessment;

/**
 * Printable HTML of the fitness assessment of one exam (the browser prints it).
 */
class FitnessAssessmentPreview extends \Hubleto\Erp\Controller
{
  public int $returnType = self::RETURN_TYPE_STRING;

  public function renderString(): string
  {
    /** @var FitnessAssessment */
    $fitnessAssessment = $this->getService(FitnessAssessment::class);
    $vars = $fitnessAssessment->getTemplateVars($this->router()->urlParamAsInteger('idExam'));
    if (count($vars) == 0) {
      http_response_code(404);
      return $this->translate('The medical exam does not exist.');
    }

    /** @var Generator */
    $generator = $this->getService(Generator::class);
    return $generator->renderTemplate(Exam::class, 0, $vars);
  }
}
