# Hlthcr Medical Exams

Medical exams (LPP): next exam date, planned exams, fitness assessment PDF, export, calendar.

Hubleto app of the Hlthcr set (occupational health service, Slovakia). Namespace: `Hubleto\App\External\WaiBlue\HlthcrMedicalExams`.
Needs: `wai-blue/hubleto-hlthcr-core`, `wai-blue/hubleto-hlthcr-branches`, `wai-blue/hubleto-hlthcr-employees`.

## Install into a Hubleto project

```bash
composer config repositories.wai-blue/hubleto-hlthcr-medical-exams vcs <repository-url>
composer require wai-blue/hubleto-hlthcr-medical-exams:dev-main
php hubleto app install "Hubleto\App\External\WaiBlue\HlthcrMedicalExams"
npm run build
```
