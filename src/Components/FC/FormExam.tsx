import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import Form, { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { type FormProps } from '@hubleto/react-ui/components/fc/FormInterfaces';
import Input from '@hubleto/react-ui/components/fc/FormComponents/Input';
import { dateToEUFormat } from '@hubleto/react-ui/components/fc/Inputs/DateTime';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';
// Shared with other Hlthcr apps (relative import: Hubleto has no alias for custom apps).
// Core is a sibling package (external_apps/ or vendor/wai-blue/).
import FileDownloadButton from '../../../../hubleto-hlthcr-core/src/Components/FC/FileDownloadButton';
import ExamHazardsEditor from './ExamHazardsEditor';
import FitnessAssessmentButtons from './FitnessAssessmentButtons';

const componentName = 'FormExam';
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams';
const T = new Translator(parentApp + '/Loader', 'Components/' + componentName);

// The clinic dropdown offers the clinics of the employee's branch (FC lookups do not send form values).
const InputClinicOfEmployee = (props: {readonly: boolean}) => {
  const idEmployee = useRecordField('id_employee', 0);
  return <Input
    key={'clinic-of-employee-' + idEmployee}
    field='id_clinic'
    readonly={props.readonly}
    customInputProps={{myself: {customEndpointParams: {idEmployee: idEmployee}}}}
  />;
}

// Employee, type and clinic stay as created (the server keeps them), so they are read-only after saving.
const TabDefault = () => {
  const form = React.useContext(FormMetaContext);
  const isSaved = form.id > 0;
  return <div className='grid grid-cols-1 md:grid-cols-2 gap-4'>
    <div>
      <Input field='id_employee' readonly={isSaved} />
      <Input field='exam_type' readonly={isSaved} />
      <div className='grid grid-cols-2 gap-2'>
        <Input field='date_exam' />
        <Input field='time_exam' />
      </div>
      <Input field='result' />
      <Input field='note' />
      <InputClinicOfEmployee readonly={isSaved} />
    </div>
    <div>
      <Input field='next_exam_type' />
      <Input field='date_next_exam' />
      <Input field='document' />
      <FileDownloadButton
        urlSlug='hlthcr-medical-exams/exams'
        fileField='document'
        downloadAction='document'
        text={T.translate('Open document')}
        noFileText={T.translate('No document is uploaded.')}
      />
      <Input field='scan' />
      <FileDownloadButton
        urlSlug='hlthcr-medical-exams/exams'
        fileField='scan'
        downloadAction='scan'
        text={T.translate('Open scan')}
        noFileText={T.translate('No scan is uploaded.')}
      />
    </div>
  </div>;
}

// The title shows the date as dd.mm.yyyy (Hubleto's title would show the stored yyyy-mm-dd).
const ExamTitle = () => {
  const date = useRecordField<string>('date_exam', '');
  return <div>
    <h2>{date ? dateToEUFormat(date) : ''}</h2>
    <small>{T.translate('Medical exam')}</small>
  </div>;
}

const FormExam = (props: FormProps) => <Form
  componentName={componentName}
  parentApp={parentApp}
  model={parentApp + '/Models/Exam'}
  urlSlug='hlthcr-medical-exams/exams'
  renderTitle={() => <ExamTitle />}
  tabs={{
    default: {title: <b>{T.translate('Medical exam')}</b>, content: () => <TabDefault />},
    hazards: {title: T.translate('Factors and examinations'), content: () => <ExamHazardsEditor />},
    fitnessAssessment: {title: T.translate('Fitness assessment'), content: () => <FitnessAssessmentButtons />},
  }}
  {...props}
/>;

export default FormExam;
