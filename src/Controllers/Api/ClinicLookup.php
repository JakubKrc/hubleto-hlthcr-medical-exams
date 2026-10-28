<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Controllers\Api;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\Clinic;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\Employee;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamAccess;

/**
 * Clinic dropdown of the exam form (legacy): clinics serving the employee's branch;
 * medical staff only their own clinics. Same output format as api/record/lookup.
 */
class ClinicLookup extends \Hubleto\Erp\Controllers\ApiController
{
  const ENDPOINT = 'hlthcr-medical-exams/api/clinic-lookup';

  public function renderJson(): array
  {
    $search = $this->router()->urlParamAsString('search');
    $idEmployee = $this->router()->urlParamAsInteger('idEmployee');

    /** @var Clinic */
    $mClinic = $this->getModel(Clinic::class);
    $query = $mClinic->record->prepareLookupQuery($search);

    /** @var ExamAccess */
    $examAccess = $this->getService(ExamAccess::class);
    if ($examAccess->seesOnlyOwnClinicExams()) {
      $query = $query->whereIn('hlthcr_clinics.id', $examAccess->getOwnClinicIds());
    } elseif ($idEmployee > 0) {
      $employee = $this->getModel(Employee::class)->record->prepareReadQuery()->where('hlthcr_employees.id', $idEmployee)->first();
      $idBranch = $employee ? (int) $employee->id_branch : 0;

      // The clinic the exam already has stays in the list, also when it no longer serves the branch.
      $formRecord = $this->router()->urlParamAsArray('formRecord');
      $idChosenClinic = (int) ($formRecord['id_clinic'] ?? 0);

      $query = $query->where(function ($offeredClinics) use ($idBranch, $idChosenClinic) {
        $offeredClinics->whereIn('hlthcr_clinics.id', function ($clinicsOfBranch) use ($idBranch) {
          $clinicsOfBranch->select('id_clinic')->from('hlthcr_branch_has_clinics')->where('id_branch', $idBranch);
        });
        if ($idChosenClinic > 0) {
          $offeredClinics->orWhere('hlthcr_clinics.id', $idChosenClinic);
        }
      });
    }

    $clinics = $mClinic->record->prepareLookupData($query->get()->toArray());
    // A list, as Hubleto's own lookup returns since Hubleto 2.
    return $clinics;
  }
}
