<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Services\ProtectedFile;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;

/**
 * Downloads the uploaded document or scan of a medical exam (route parameter "file": document or scan).
 * Both follow the exam's visibility.
 */
class DownloadExamFile extends \Hubleto\Erp\Controller
{
  // Raw file or plain-text answer, without the Hubleto desktop around it.
  public int $returnType = self::RETURN_TYPE_STRING;

  public function renderString(): string
  {
    /** @var ProtectedFile $protectedFile */
    $protectedFile = $this->getService(ProtectedFile::class);
    $idExam = $this->router()->urlParamAsInteger('idExam');
    $fileColumn = $this->router()->urlParamAsString('file') == 'scan' ? 'scan' : 'document';

    $exam = $this->getModel(Exam::class)->record->prepareReadQuery()->where('hlthcr_exams.id', $idExam)->first();
    if (!$exam) {
      return $protectedFile->refuse(404, $this->translate('The medical exam does not exist.'));
    }

    if ((string) $exam->{$fileColumn} == '') {
      return $protectedFile->refuse(404, $this->translate('No file is uploaded.'));
    }

    return $protectedFile->send((string) $exam->{$fileColumn});
  }
}
