<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\RecordManagers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Traits\DefaultOrder;
use Hubleto\App\Community\Auth\Models\RecordManagers\User;
use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Clinic;
use Hubleto\App\External\WaiBlue\HlthcrEmployees\Models\RecordManagers\Employee;
use Hubleto\App\External\WaiBlue\HlthcrBranches\Models\RecordManagers\Job;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends \Hubleto\Erp\RecordManager
{
  use DefaultOrder;

  public $table = 'hlthcr_exams';

  const PERIOD_FILTER = 'fExamPeriod';
  const PERIOD_FILTER_ALL = 0;
  const PERIOD_FILTER_UPCOMING = 1;
  const PERIOD_FILTER_PAST = 2;

  public function EMPLOYEE(): BelongsTo
  {
    return $this->belongsTo(Employee::class, 'id_employee', 'id');
  }

  public function JOB(): BelongsTo
  {
    return $this->belongsTo(Job::class, 'id_job', 'id');
  }

  public function PLANNED_EXAM(): BelongsTo
  {
    return $this->belongsTo(Exam::class, 'id_planned_exam', 'id');
  }

  public function CLINIC(): BelongsTo
  {
    return $this->belongsTo(Clinic::class, 'id_clinic', 'id');
  }

  public function AUTHOR(): BelongsTo
  {
    return $this->belongsTo(User::class, 'id_author', 'id');
  }

  public function HAZARDS(): HasMany
  {
    return $this->hasMany(ExamHasHazard::class, 'id_exam', 'id');
  }

  public function prepareReadQuery(mixed $query = null, int $level = 0, array|null $includeRelations = null): mixed
  {
    $query = parent::prepareReadQuery($query, $level, $includeRelations);
    return $this->restrictToVisibleExams($query);
  }

  // Dropdowns too: Hubleto's lookup query does not go through prepareReadQuery.
  public function prepareLookupQuery(string $search): mixed
  {
    $query = parent::prepareLookupQuery($search);
    return $this->restrictToVisibleExams($query);
  }

  private function restrictToVisibleExams(mixed $query): mixed
  {
    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    return $hubleto->getService(ExamAccess::class)->restrictToVisibleExams($query, $this->table);
  }

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);

    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    $router = $hubleto->router();

    // Employee form tab "Lekárske prehliadky".
    $idEmployee = $router->urlParamAsInteger('idEmployee');
    if ($idEmployee > 0) {
      $query = $query->where($this->table . '.id_employee', $idEmployee);
    }

    $filters = $router->urlParamAsArray('filters');
    $period = (int) ($filters[self::PERIOD_FILTER] ?? self::PERIOD_FILTER_ALL);
    if ($period == self::PERIOD_FILTER_UPCOMING) $query = $query->where($this->table . '.date_exam', '>=', date('Y-m-d'));
    if ($period == self::PERIOD_FILTER_PAST) $query = $query->where($this->table . '.date_exam', '<', date('Y-m-d'));

    return $query;
  }

  public function prepareLookupData(array $dataRaw): array
  {
    return parent::prepareLookupData($dataRaw);
  }
}
