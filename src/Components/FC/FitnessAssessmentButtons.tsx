import React from 'react';
import Translator from '@hubleto/react-ui/core/Translator';
import request from '@hubleto/react-ui/core/Request';
import { FormMetaContext } from '@hubleto/react-ui/components/fc/Form';
import { useRecordField } from '@hubleto/react-ui/components/fc/FormRecordStore';

const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams';
const T = new Translator(parentApp + '/Loader', 'Components/FitnessAssessmentButtons');

/**
 * The fitness assessment of a saved exam: printable preview, PDF generation (each run replaces the PDF) and download.
 */
const FitnessAssessmentButtons = () => {
  const form = React.useContext(FormMetaContext);
  const savedPdfFile = useRecordField<string>('fitness_assessment_file', '');
  const [hasPdf, setHasPdf] = React.useState(false);
  const [message, setMessage] = React.useState('');

  if (form.id <= 0) return <div className='alert alert-info'>{T.translate('Save the medical exam first.')}</div>;

  const examUrl = globalThis.hubleto.config.projectUrl + '/hlthcr-medical-exams/exams/' + form.id;
  const pdfExists = hasPdf || (savedPdfFile ?? '') !== '';

  function showGenerationResult(response: any) {
    if (response.status === 'success') {
      setHasPdf(true);
      setMessage(T.translate('The PDF was generated.'));
    } else {
      setMessage(response.message ?? T.translate('The PDF could not be generated.'));
    }
  }

  function generatePdf() {
    setMessage('');
    request.get('hlthcr-medical-exams/api/generate-fitness-assessment', {idExam: form.id}, showGenerationResult);
  }

  return <div className='flex flex-col gap-2'>
    <div className='flex gap-2 flex-wrap items-center'>
      <a className='btn btn-transparent' href={examUrl + '/fitness-assessment'} target='_blank'>
        <span className='icon'><i className='fas fa-print'></i></span>
        <span className='text'>{T.translate('Print preview')}</span>
      </a>
      <button className='btn btn-primary' onClick={generatePdf}>
        <span className='icon'><i className='fas fa-file-pdf'></i></span>
        <span className='text'>{T.translate('Generate PDF')}</span>
      </button>
      {pdfExists ? <a className='btn btn-transparent' href={examUrl + '/fitness-assessment/download'} target='_blank'>
        <span className='icon'><i className='fas fa-download'></i></span>
        <span className='text'>{T.translate('Download PDF')}</span>
      </a> : null}
    </div>
    {message !== '' ? <div>{message}</div> : null}
  </div>;
}

export default FitnessAssessmentButtons;
