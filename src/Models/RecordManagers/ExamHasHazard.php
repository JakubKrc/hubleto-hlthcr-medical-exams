<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\RecordManagers;

use Hubleto\App\External\WaiBlue\HlthcrCore\Models\RecordManagers\Hazard;
use Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Services\ExamAccess;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamHasHazard extends \Hubleto\Erp\RecordManager
{
  public $table = 'hlthcr_exam_has_hazards';

  public function EXAM(): BelongsTo
  {
    return $this->belongsTo(Exam::class, 'id_exam', 'id');
  }

  public function HAZARD(): BelongsTo
  {
    return $this->belongsTo(Hazard::class, 'id_hazard', 'id');
  }

  public function prepareReadQuery(mixed $query = null, int $level = 0, array|null $includeRelations = null): mixed
  {
    $query = parent::prepareReadQuery($query, $level, $includeRelations);
    return $this->restrictToVisibleExams($query);
  }

  public function prepareLookupQuery(string $search): mixed
  {
    $query = parent::prepareLookupQuery($search);
    return $this->restrictToVisibleExams($query);
  }

  // Visible with its exam.
  private function restrictToVisibleExams(mixed $query): mixed
  {
    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    /** @var ExamAccess */
    $examAccess = $hubleto->getService(ExamAccess::class);
    return $query->whereIn($this->table . '.id_exam', function ($visibleExams) use ($examAccess) {
      $visibleExams->select('hlthcr_exams.id')->from('hlthcr_exams');
      // The subquery builder is changed in place, so the returned value is not needed.
      $examAccess->restrictToVisibleExams($visibleExams, 'hlthcr_exams');
    });
  }

  public function addUrlFiltersToQuery(mixed $query): mixed
  {
    $query = parent::addUrlFiltersToQuery($query);

    $hubleto = \Hubleto\Erp\Loader::getGlobalApp();
    $idExam = $hubleto->router()->urlParamAsInteger('idExam');
    if ($idExam > 0) {
      $query = $query->where($this->table . '.id_exam', $idExam);
    }

    return $query;
  }

  public function prepareLookupData(array $dataRaw): array
  {
    return parent::prepareLookupData($dataRaw);
  }
}
