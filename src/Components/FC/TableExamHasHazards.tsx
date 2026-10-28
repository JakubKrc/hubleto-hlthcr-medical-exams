import Table from '@hubleto/react-ui/components/fc/Table'
import { type TableMeta, type TableProps } from '@hubleto/react-ui/components/fc/TableInterfaces'
import FormExamHasHazard from './FormExamHasHazard'

interface TableExamHasHazardsProps extends TableProps {
  idExam?: number,
}

const componentName = 'TableExamHasHazards'
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams'

const TableExamHasHazards = (props: TableExamHasHazardsProps) => {
  const idExam = props.idExam ?? 0;
  return <Table
    componentName={componentName}
    parentApp={parentApp}
    model={parentApp + '/Models/ExamHasHazard'}
    baseUrlSlug='hlthcr-medical-exams/exam-has-hazards'
    endpointParams={{idExam: idExam}}
    formDefaultValues={idExam > 0 ? {id_exam: idExam} : {}}
    formModalProps={{type: 'right wide'}}
    renderForm={(table: TableMeta) => <FormExamHasHazard {...table.getDefaultFormProps()} />}
    {...props}
  />;
}

export default TableExamHasHazards;
