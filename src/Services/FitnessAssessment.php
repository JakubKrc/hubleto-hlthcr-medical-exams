<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\Community\Customers\Models\Customer;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Branch;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\Job;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Clinic;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Hazard;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Exam;

/**
 * The fitness assessment ("lekársky posudok") both legacy apps print for an exam.
 * Builds the template variables and the default HTML template for Hubleto's document Generator.
 */
class FitnessAssessment extends \Hubleto\Erp\Core
{
  public string $translationContext = 'hubleto-app-external-waiblue-hlthcrmedicalexams-loader';
  public string $translationContextInner = 'Services\\FitnessAssessment';

  /**
   * Variables of one exam the user may see; empty when the exam is not visible.
   */
  public function getTemplateVars(int $idExam): array
  {
    /** @var Exam */
    $mExam = $this->getModel(Exam::class);
    $exam = $mExam->record->prepareReadQuery()->where('hlthcr_exams.id', $idExam)->first();
    if (!$exam) return [];

    $employee = $this->getModel(Employee::class)->record->where('id', $exam->id_employee)->first();
    $branch = $employee ? $this->getModel(Branch::class)->record->where('id', $employee->id_branch)->first() : null;
    $customer = $branch ? $this->getModel(Customer::class)->record->where('id', $branch->id_customer)->first() : null;
    $job = $employee ? $this->getModel(Job::class)->record->where('id', $employee->id_job)->first() : null;
    $clinic = $this->getModel(Clinic::class)->record->where('id', $exam->id_clinic)->first();

    return [
      'exam' => [
        'id' => (int) $exam->id,
        'type' => $mExam->getTypeLabels()[(int) $exam->exam_type] ?? '',
        'date' => $this->formatDate((string) $exam->date_exam),
        'result' => $mExam->getResultLabels()[(int) $exam->result] ?? '',
        'next_date' => $this->formatDate((string) $exam->date_next_exam),
        'note' => (string) $exam->note,
      ],
      'employee' => [
        'name' => $employee ? $employee->name : '',
        'date_of_birth' => $employee ? $this->formatDate((string) $employee->date_of_birth) : '',
        'personal_number' => $employee ? (string) $employee->personal_number : '',
        'address' => $employee ? trim($employee->street . ', ' . $employee->postal_code . ' ' . $employee->city, ' ,') : '',
      ],
      'employer' => [
        'name' => $customer ? $customer->name : '',
        'company_id' => $customer ? (string) $customer->company_id : '',
        'address' => $customer ? trim($customer->street_line_1 . ', ' . $customer->postal_code . ' ' . $customer->city, ' ,') : '',
        'branch' => $branch ? $branch->name : '',
      ],
      'job' => $job ? $job->name : '',
      'clinic' => $clinic ? trim($clinic->name . ', ' . $clinic->street . ', ' . $clinic->city, ' ,') : '',
      'hazardsByType' => $this->getHazardsByType($idExam),
    ];
  }

  // The exam's snapshot grouped by the four hazard types: [type name => [['name', 'category']]].
  private function getHazardsByType(int $idExam): array
  {
    /** @var ExamHazards */
    $examHazards = $this->getService(ExamHazards::class);
    $levelsByHazard = $examHazards->loadSnapshotLevels($idExam);
    if (count($levelsByHazard) == 0) return [];

    /** @var Hazard */
    $mHazard = $this->getModel(Hazard::class);
    $typeNames = $mHazard->getTypeNames();
    $hazards = $mHazard->record->whereIn('id', array_keys($levelsByHazard))->orderBy('type')->orderBy('name')->get();

    $hazardsByType = [];
    foreach ($hazards as $hazard) {
      $category = '';
      if (Hazard::typeUsesCategories((int) $hazard->type)) {
        $category = (string) $levelsByHazard[(int) $hazard->id];
      }
      $hazardsByType[$typeNames[(int) $hazard->type]][] = ['name' => $hazard->name, 'category' => $category];
    }
    return $hazardsByType;
  }

  private function formatDate(string $date): string
  {
    if ($date == '') return '';
    return date('d.m.Y', strtotime($date));
  }

  /**
   * Default Twig template; an administrator may replace it with a template in the Documents app.
   * DejaVu Sans comes with the PDF library and has all Slovak letters (the default font has not).
   */
  public function getDefaultTemplate(): string
  {
    return '
      {{ defaultStyle|raw }}
      <style>body, h2, h3, p, li { font-family: "DejaVu Sans", sans-serif; }</style>
      <h2>' . $this->translate('Medical fitness assessment') . '</h2>
      <p>' . $this->translate('Medical exam') . ': {{ exam.type }}, {{ exam.date }}</p>
      <h3>' . $this->translate('Employer') . '</h3>
      <p>{{ employer.name }}, ' . $this->translate('Company ID') . ' {{ employer.company_id }}<br/>{{ employer.address }}<br/>
        ' . $this->translate('Branch') . ': {{ employer.branch }}</p>
      <h3>' . $this->translate('Employee') . '</h3>
      <p>{{ employee.name }}, ' . $this->translate('born') . ' {{ employee.date_of_birth }}<br/>
        ' . $this->translate('Personal number') . ': {{ employee.personal_number }}<br/>{{ employee.address }}<br/>
        ' . $this->translate('Job position') . ': {{ job }}</p>
      {% for typeName, hazards in hazardsByType %}
        <h3>{{ typeName }}</h3>
        <ul>
          {% for hazard in hazards %}
            <li>{{ hazard.name }}{% if hazard.category %} (' . $this->translate('category') . ' {{ hazard.category }}){% endif %}</li>
          {% endfor %}
        </ul>
      {% endfor %}
      <h3>' . $this->translate('Result') . '</h3>
      <p><b>{{ exam.result }}</b></p>
      {% if exam.next_date %}<p>' . $this->translate('Next medical exam') . ': {{ exam.next_date }}</p>{% endif %}
      {% if exam.note %}<p>{{ exam.note }}</p>{% endif %}
      <p>' . $this->translate('Clinic') . ': {{ clinic }}</p>
      <br/><br/>
      <p>' . $this->translate('Date and signature of the doctor') . ': ............................................</p>
    ';
  }
}
