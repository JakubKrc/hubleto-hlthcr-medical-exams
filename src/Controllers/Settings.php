<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers;

use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\FitnessAssessment;

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

    /** @var \Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles */
    $roles = $this->getService(\Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles::class);
    $maySaveSettings = $roles->isActiveUserAdministratorOrManager();
    $this->viewParams['maySaveSettings'] = $maySaveSettings;

    /** @var FitnessAssessment */
    $fitnessAssessment = $this->getService(FitnessAssessment::class);
    $templateNames = $fitnessAssessment->getTemplateNames();

    $settingsChanged = $this->router()->urlParamAsBool('settingsChanged');
    if ($settingsChanged && $maySaveSettings) {
      $idTemplate = $this->router()->urlParamAsInteger('fitnessAssessmentTemplateId');
      $isKnownTemplate = $idTemplate == 0 || isset($templateNames[$idTemplate]);
      if ($isKnownTemplate) {
        $fitnessAssessment->saveTemplateId($idTemplate);
        $this->viewParams['settingsSaved'] = true;
      }
    }

    // The hazard catalogue is in HlthcrCore, which medical staff do not have.
    $coreApp = $this->appManager()->getApp('Hubleto\\App\\External\\WaiBlue\\HlthcrCore');
    $this->viewParams['mayOpenHazards'] = $coreApp !== null && $this->permissionsManager()->isAppPermittedForActiveUser($coreApp);

    $this->viewParams['templateNames'] = $templateNames;
    $this->viewParams['fitnessAssessmentTemplateId'] = $fitnessAssessment->getTemplateId();
    $this->setView('@Hubleto:App:External:WaiBlue:HlthcrMedicalExams/Settings.twig');
  }
}
