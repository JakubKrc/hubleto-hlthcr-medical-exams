import Translator from '@hubleto/react-ui/core/Translator';
import Form from '@hubleto/react-ui/components/fc/Form';
import { type FormProps } from '@hubleto/react-ui/components/fc/FormInterfaces';
import Input from '@hubleto/react-ui/components/fc/FormComponents/Input';

const componentName = 'FormExamHasHazard';
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams';
const T = new Translator(parentApp + '/Loader', 'Components/' + componentName);

const FormExamHasHazard = (props: FormProps) => <Form
  componentName={componentName}
  parentApp={parentApp}
  model={parentApp + '/Models/ExamHasHazard'}
  urlSlug='hlthcr-medical-exams/exam-has-hazards'
  title={{ sub: T.translate('Hazard of the medical exam')}}
  tabs={{default: {content: () => <div className='grid grid-cols-1 md:grid-cols-2 gap-2'>
    <Input field='id_exam' />
    <Input field='id_hazard' />
    <Input field='level' />
  </div>}}}
  {...props}
/>

export default FormExamHasHazard;
