<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamExport;

class DownloadExamExport extends \Hubleto\Erp\Controller
{
  // Raw CSV, without the Hubleto desktop around it.
  public int $returnType = self::RETURN_TYPE_STRING;

  public function renderString(): string
  {
    $router = $this->router();
    $dateFrom = $router->urlParamAsString('dateFrom');
    $dateTo = $router->urlParamAsString('dateTo');

    $hasValidPeriod = strtotime($dateFrom) !== false && strtotime($dateTo) !== false;
    if (!$hasValidPeriod) {
      header('Content-Type: text/plain; charset=utf-8');
      return $this->translate('Fill in the period.');
    }

    $csv = $this->getService(ExamExport::class)->buildCsv(
      date('Y-m-d', strtotime($dateFrom)),
      date('Y-m-d', strtotime($dateTo)),
      $router->urlParamAsInteger('idBranch'),
      $router->urlParamAsInteger('examType', ExamExport::ANY),
      $router->urlParamAsInteger('result', ExamExport::ANY)
    );

    header('Content-Type: text/csv; charset=windows-1250');
    header('Content-Disposition: attachment; filename="ExportLekarskychPrehliadok_' . date('Y-m-d') . '.csv"');
    return $csv;
  }
}
