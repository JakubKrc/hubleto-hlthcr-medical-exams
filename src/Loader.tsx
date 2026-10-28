import React from 'react';
import App from '@hubleto/react-ui/core/App'
import Translator from '@hubleto/react-ui/core/Translator';
import FormCustomizer from '@hubleto/react-ui/core/FormCustomizer';
import { type FormMeta } from '@hubleto/react-ui/components/fc/FormInterfaces';
import TableExams from './Components/FC/TableExams'
import TableExamHasHazards from './Components/FC/TableExamHasHazards'

const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams';
const T = new Translator(parentApp + '/Loader', 'Loader');

// "Lekárske prehliadky" tab in the employee form (HlthcrEmployees).
function mountEmployeeExamsTab() {
  return {
    title: T.translate('Medical exams'),
    content: (form: FormMeta) => {
      if (form.id <= 0) return <div className='alert alert-info'>{T.translate('Save the employee first.')}</div>;
      return <TableExams uid={form.uid + '_table_exams'} parentForm={form} idEmployee={form.id} />;
    },
  };
}

class HlthcrMedicalExamsApp extends App {
  init() {
    super.init();
    globalThis.hubleto.registerReactComponent('HlthcrMedicalExamsTableExams', TableExams);
    globalThis.hubleto.registerReactComponent('HlthcrMedicalExamsTableExamHasHazards', TableExamHasHazards);
    FormCustomizer.addTab('FormEmployee', 'hlthcr-medical-exams', mountEmployeeExamsTab);
  }
}

globalThis.hubleto.registerApp(parentApp, new HlthcrMedicalExamsApp());
