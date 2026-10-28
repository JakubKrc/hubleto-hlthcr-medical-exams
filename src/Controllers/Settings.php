<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

class Settings extends \Hubleto\Erp\Controller
{
  public function getBreadcrumbs(): array
  {
    return array_merge(parent::getBreadcrumbs(), [
      ['url' => 'hlthcr-medical-exams', 'content' => $this->translate('Medical exams')],
      ['url' => 'settings', 'content' => $this->translate('Settings')],
    ]);
  }

  public function prepareView(): void
  {
    parent::prepareView();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrMedicalExams/Settings.twig');
  }
}
