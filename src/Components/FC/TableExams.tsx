import Table from '@hubleto/react-ui/components/fc/Table'
import { type TableMeta, type TableProps } from '@hubleto/react-ui/components/fc/TableInterfaces'
import FormExam from './FormExam'
import { dateToEUFormat } from '@hubleto/react-ui/components/fc/Inputs/DateTime'

interface TableExamsProps extends TableProps {
  idEmployee?: number,
}

const componentName = 'TableExams'
const parentApp = 'Hubleto/App/External/WaiBlue/HlthcrMedicalExams'
const RESULT_UNDETERMINED = 0;

// Legacy warnings in both apps: a past exam without a result is red, an exam of an inactive employee grey.
function isEmployeeInactive(exam: any): boolean {
  return exam.EMPLOYEE && !Number(exam.EMPLOYEE.is_active);
}

// Today's date on the user's computer as YYYY-MM-DD (toISOString() would give the UTC date, a day off around midnight).
function getLocalToday(): string {
  const now = new Date();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return now.getFullYear() + '-' + month + '-' + day;
}

function isOverdueWithoutResult(exam: any): boolean {
  const today = getLocalToday();
  const isPast = (exam.date_exam ?? '') !== '' && exam.date_exam < today;
  return isPast && Number(exam.result ?? 0) === RESULT_UNDETERMINED && !isEmployeeInactive(exam);
}

// Inside the employee's own tab every row is that employee, so the grey row would say nothing.
function getExamRowClassName(exam: any, idEmployee: number): string {
  const isListOfAllExams = idEmployee == 0;
  if (isListOfAllExams && isEmployeeInactive(exam)) return 'bg-gray-300';
  return '';
}

function renderExamCell(table: TableMeta, columnName: string, column: any, data: any, options: any) {
  const isWarningCell = columnName == 'date_exam' && !options.renderEditor && isOverdueWithoutResult(data);
  if (isWarningCell) return <span className='bg-red-600 text-white px-1'>{dateToEUFormat(data.date_exam)}</span>;
  return table.renderDefaultCell(columnName, column, data, options);
}

// The medical exams list and the "Lekárske prehliadky" tab of the employee form.
const TableExams = (props: TableExamsProps) => {
  const idEmployee = props.idEmployee ?? 0;
  return <Table
    componentName={componentName}
    parentApp={parentApp}
    model={parentApp + '/Models/Exam'}
    baseUrlSlug='hlthcr-medical-exams/exams'
    endpointParams={{idEmployee: idEmployee}}
    formDefaultValues={idEmployee > 0 ? {id_employee: idEmployee} : {}}
    formModalProps={{type: 'right wide'}}
    renderForm={(table: TableMeta) => <FormExam {...table.getDefaultFormProps()} />}
    getRowClassName={(table: TableMeta, exam: any) => getExamRowClassName(exam, idEmployee)}
    renderCell={renderExamCell}
    {...props}
  />;
}

export default TableExams;
