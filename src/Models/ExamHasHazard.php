<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Hazard;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Traits\HasHazardLevel;
use Hubleto\Framework\Db\Column\Integer;
use Hubleto\Framework\Db\Column\Lookup;

/**
 * One factor, hazard or examination of a medical exam, as submitted in the exam form (legacy lpp_risks).
 * Level 1–4 for hazard types with categories, 1 ("yes") for the others.
 */
class ExamHasHazard extends \Hubleto\App\External\WaiBlue\HlthcrCore\Models\HlthcrModel
{
  use HasHazardLevel;

  public string $table = 'hlthcr_exam_has_hazards';
  public string $recordManagerClass = RecordManagers\ExamHasHazard::class;
  public ?string $lookupSqlValue = '(select name from hlthcr_hazards where hlthcr_hazards.id = {%TABLE%}.id_hazard)';
  public ?string $lookupUrlDetail = 'hlthcr-medical-exams/exam-has-hazards/{%ID%}';

  public array $relations = [
    'EXAM' => [ self::BELONGS_TO, Exam::class, 'id_exam', 'id' ],
    'HAZARD' => [ self::BELONGS_TO, Hazard::class, 'id_hazard', 'id' ],
  ];

  public function describeColumns(): array
  {
    return array_merge(parent::describeColumns(), [
      'id_exam' => (new Lookup($this, $this->translate('Medical exam'), Exam::class, 'CASCADE'))->setRequired(),
      'id_hazard' => (new Lookup($this, $this->translate('Hazard'), Hazard::class))->setRequired()->setDefaultVisible(),
      'level' => (new Integer($this, $this->translate('Level')))
        ->setRequired()
        ->setDefaultVisible()
        ->setDefaultValue(Hazard::LEVEL_YES)
        ->setEnumValues([
          1 => $this->translate('Category 1'),
          2 => $this->translate('Category 2'),
          3 => $this->translate('Category 3'),
          4 => $this->translate('Category 4'),
        ])
      ,
    ]);
  }

  public function onBeforeCreate(array $record): array
  {
    $record = parent::onBeforeCreate($record);
    $this->refuseInvalidHazardLevel($record);
    return $record;
  }

  public function onBeforeUpdate(array $record): array
  {
    $record = parent::onBeforeUpdate($record);
    $this->refuseInvalidHazardLevel($record);
    return $record;
  }

  public function describeTable(): \Hubleto\Framework\Description\Table
  {
    $description = parent::describeTable();
    $description->ui['addButtonText'] = $this->translate('Add hazard');
    $description->show(['header', 'fulltextSearch', 'columnSearch', 'moreActionsButton']);
    $description->hide(['footer']);
    return $description;
  }

  public function describeForm(): \Hubleto\Framework\Description\Form
  {
    return parent::describeForm();
  }
}
