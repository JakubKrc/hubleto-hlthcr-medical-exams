<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

class ExamHasHazards extends \Hubleto\Erp\Controller
{
  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrMedicalExams/ExamHasHazards.twig');
  }
}
