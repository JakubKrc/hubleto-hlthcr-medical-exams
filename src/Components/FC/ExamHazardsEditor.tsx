import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import request from '@hubleto/react-ui/core/Request';
import { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';

interface ExamHasHazard {
  id: number,
  name: string,
  level: number,
}

interface ExamHazardType {
  id: number,
  name: string,
  uses_categories: boolean,
  is_exam_scope: boolean,
  hazards: Array<ExamHasHazard>,
}

const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams';
const T = new Translator(parentApp + '/Loader', 'Components/ExamHazardsEditor');

// Same values as Hazard::HIGHEST_CATEGORY and "not present" in PHP.
const LEVEL_NOT_PRESENT = 0;
const LEVEL_YES = 1;
const CATEGORY_LEVELS = [LEVEL_NOT_PRESENT, 1, 2, 3, 4];

// Submitted with the exam form as JSON {idHazard: level} (Exam::SUBMITTED_HAZARDS_FIELD).
const SUBMITTED_HAZARDS_FIELD = 'hazard_levels';

/**
 * Factors, hazards and examinations of a medical exam (legacy "Faktory" card of the exam form).
 * Pre-filled from the employee's job until the exam has its own saved snapshot; then the snapshot is shown.
 */
const ExamHazardsEditor = () => {
  const form = React.useContext(FormMetaContext);
  const idEmployee = Number(useRecordField('id_employee', 0) ?? 0);
  const [hazardTypes, setHazardTypes] = React.useState<Array<ExamHazardType>>([]);
  const [levels, setLevels] = React.useState<Record<number, number>>({});
  const [isFromJob, setIsFromJob] = React.useState(false);

  function submitWithForm(changedLevels: Record<number, number>) {
    form.changeRecord({[SUBMITTED_HAZARDS_FIELD]: JSON.stringify(changedLevels)});
  }

  function applyLoadedHazards(response: any) {
    const loadedHazardTypes: Array<ExamHazardType> = response.hazardTypes ?? [];
    const loadedLevels: Record<number, number> = {};
    for (const hazardType of loadedHazardTypes) {
      for (const hazard of hazardType.hazards) loadedLevels[hazard.id] = hazard.level;
    }
    setHazardTypes(loadedHazardTypes);
    setLevels(loadedLevels);
    setIsFromJob(response.isFromJob === true);

    // Values taken from the job are stored only when the exam is saved (legacy warning below).
    if (response.isFromJob === true) submitWithForm(loadedLevels);
  }

  React.useEffect(() => {
    request.get('hlthcr-medical-exams/api/get-exam-hazards', {idExam: form.id, idEmployee: idEmployee}, applyLoadedHazards);
  }, [form.id, idEmployee]);

  function changeLevel(idHazard: number, level: number) {
    const changedLevels = {...levels, [idHazard]: level};
    setLevels(changedLevels);
    submitWithForm(changedLevels);
  }

  function renderLevelInput(hazardType: ExamHazardType, hazard: ExamHasHazard) {
    const currentLevel = levels[hazard.id] ?? LEVEL_NOT_PRESENT;

    if (!hazardType.uses_categories) {
      return <input
        type='checkbox'
        id={'exam-hazard-' + hazard.id}
        checked={currentLevel !== LEVEL_NOT_PRESENT}
        onChange={(event) => changeLevel(hazard.id, event.target.checked ? LEVEL_YES : LEVEL_NOT_PRESENT)}
      />;
    }

    return <select id={'exam-hazard-' + hazard.id} value={currentLevel} onChange={(event) => changeLevel(hazard.id, Number(event.target.value))}>
      {CATEGORY_LEVELS.map((level) => <option key={level} value={level}>
        {level === LEVEL_NOT_PRESENT ? T.translate('Category not set') : T.translate('Category') + ' ' + level}
      </option>)}
    </select>;
  }

  return <div className='flex flex-col gap-4'>
    {isFromJob ? <div className='alert alert-warning'>
      {T.translate('Factors were taken from the job position but are not saved in the medical exam yet. Save the medical exam.')}
    </div> : null}
    {hazardTypes.map((hazardType) => <div key={hazardType.id} className='card'>
      <div className='card-header'>{hazardType.name}</div>
      <div className='card-body'>
        <table className='table-default w-full'>
          <tbody>
            {hazardType.hazards.map((hazard) => <tr key={hazard.id}>
              <td className='py-1'><label htmlFor={'exam-hazard-' + hazard.id} className='cursor-pointer'>{hazard.name}</label></td>
              <td className='py-1 w-64'>{renderLevelInput(hazardType, hazard)}</td>
            </tr>)}
          </tbody>
        </table>
      </div>
    </div>)}
  </div>;
}

export default ExamHazardsEditor;
