<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services;

use Hubleto\App\External\WaiBlue\HlthcrBranches\Services\BranchScope;
use Hubleto\App\External\WaiBlue\HlthcrCore\Services\Roles;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\ClinicHasMedicalStaff;

/**
 * Who sees which medical exam (legacy exam list rules):
 * administrators, managers and nurses all; hygienists exams of employees of own branches;
 * medical staff exams at their own clinics.
 */
class ExamAccess extends \Hubleto\Erp\Core
{
  private function roles(): Roles
  {
    return $this->getService(Roles::class);
  }

  // Legacy: medical staff ("Zdravotný personál") see and plan exams of their own clinic only.
  public function seesOnlyOwnClinicExams(): bool
  {
    $roles = $this->roles();
    if ($roles->isActiveUserAdministratorOrManager()) return false;
    if ($roles->activeUserHasRole(Roles::NURSE) || $roles->activeUserHasRole(Roles::HYGIENIST)) return false;
    return $roles->activeUserHasRole(Roles::MEDICAL_STAFF);
  }

  // Clinics where the logged-in user is medical staff.
  public function getOwnClinicIds(): array
  {
    return $this->getModel(ClinicHasMedicalStaff::class)->record
      ->where('id_user', $this->authProvider()->getUserId())
      ->orderBy('id_clinic')
      ->pluck('id_clinic')
      ->toArray()
    ;
  }

  // The first own clinic (legacy getPersonalAmbulanceId); 0 when none.
  public function getOwnClinicId(): int
  {
    return (int) ($this->getOwnClinicIds()[0] ?? 0);
  }

  public function restrictToVisibleExams(mixed $query, string $examTable): mixed
  {
    if ($this->seesOnlyOwnClinicExams()) {
      return $query->whereIn($examTable . '.id_clinic', $this->getOwnClinicIds());
    }

    // Hygienists: employees of own branches. For other roles BranchScope does not restrict.
    /** @var BranchScope */
    $branchScope = $this->getService(BranchScope::class);
    return $query->whereIn($examTable . '.id_employee', function ($visibleEmployees) use ($branchScope) {
      $visibleEmployees->select('hlthcr_employees.id')->from('hlthcr_employees');
      // The subquery builder is changed in place, so the returned value is not needed.
      $branchScope->restrictToVisibleBranches($visibleEmployees, 'hlthcr_employees.id_branch');
    });
  }
}
