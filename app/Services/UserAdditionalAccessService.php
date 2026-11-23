<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;

class UserAdditionalAccessService
{
    private const MENU_ITEMS = [
        'travel-spd-create' => ['label' => 'Create your own SPD', 'access' => 'travel-spd-create'],
        'travel-spd-view' => ['label' => 'View department SPD', 'access' => 'travel-spd-view'],
        'travel-spd-manage' => ['label' => 'Manage the department\'s SPD draft', 'access' => 'travel-spd-manage'],
        'travel-lpj-create' => ['label' => 'Create your own LPJ', 'access' => 'travel-lpj-create'],
        'travel-lpj-view' => ['label' => 'View department LPJ', 'access' => 'travel-lpj-view'],
        'travel-lpj-manage' => ['label' => 'Manage department LPJ drafts', 'access' => 'travel-lpj-manage'],
        'travel-approve' => ['label' => 'Approve assigned SPD and LPJ', 'access' => 'travel-approve'],
        'travel-configure' => ['label' => 'Manage Master Approval SPD/LPJ', 'access' => 'travel-configure'],
        'inventory-manage' => ['label' => 'Manage Inventory', 'access' => 'inventory-manage'],
        'inventory-view-all' => ['label' => 'View all entities', 'access' => 'inventory-view-all'],
        'inventory-department-issue' => ['label' => 'Issue stock to departments', 'access' => 'inventory-department-issue'],
        'scm-requests' => ['label' => 'PR dan SR', 'access' => 'scm-requests'],
        'department-budgets' => ['label' => 'Anggaran departemen', 'access' => 'scm-requests'],
        'scm-material-register' => ['label' => 'Rekap Material PR', 'access' => 'scm-material-register'],
        'scm-process' => ['label' => 'Proses Pengadaan SCM', 'access' => 'scm-process'],
        'ga-process' => ['label' => 'Purchasing Officer (HO KPMOG/APCA)', 'access' => 'ga-process'],
        'scm-process-kpmog' => ['label' => 'Pengadaan Project KPMOG', 'access' => 'scm-process-kpmog'],
        'scm-process-apca' => ['label' => 'Pengadaan Project APCA', 'access' => 'scm-process-apca'],
        'scm-configure' => ['label' => 'Pengaturan SCM', 'access' => 'scm-configure'],
        'finance-payments' => ['label' => 'PO Payment', 'access' => 'finance-payments'],
        'finance-coa' => ['label' => 'Master COA', 'access' => 'finance-coa'],
        'finance-budget' => ['label' => 'Budget Settings', 'access' => 'finance-budget'],
        'it-devices-view' => ['label' => 'View Device Catalog', 'access' => 'it-devices-view'],
        'it-devices-manage' => ['label' => 'Manage Device Catalog', 'access' => 'it-devices-manage'],
        'it-inspections-view' => ['label' => 'View Inspections', 'access' => 'it-inspections-view'],
        'it-inspections-create' => ['label' => 'Record Inspections', 'access' => 'it-inspections-create'],
        'it-inspections-manage' => ['label' => 'Manage Inspections', 'access' => 'it-inspections-manage'],
        'it-followups-manage' => ['label' => 'Manage Follow-ups', 'access' => 'it-followups-manage'],
        'it-reports-view' => ['label' => 'View Summary & Reports', 'access' => 'it-reports-view'],
        'it-reports-export' => ['label' => 'Export Reports to Excel', 'access' => 'it-reports-export'],
        'subject-categories' => ['label' => 'Subject Categories', 'access' => 'master-subject-categories'],
        'permit-kbli' => ['label' => 'Permit & KBLI', 'access' => 'master-permit-kbli'],
        'projects' => ['label' => 'Projects', 'access' => 'master-projects'],
        'activity-categories' => ['label' => 'Activity Categories', 'access' => 'master-activity-categories'],
        'departments' => ['label' => 'Departments', 'access' => 'master-departments'],
        'employees' => ['label' => 'Employees', 'access' => 'master-employees'],
        'attendance-report-center' => ['label' => 'Attendance Report', 'access' => 'attendance-report-center'],
        'attendance-upload-period' => ['label' => 'Upload Period', 'access' => 'attendance-upload-period'],
        'attendance-report-results' => ['label' => 'Report Results', 'access' => 'attendance-report-results'],
        'work-locations' => ['label' => 'Work Locations', 'access' => 'master-work-locations'],
        'report-overview' => ['label' => 'Overview', 'access' => 'report-overview'],
        'report-service-desk' => ['label' => 'Service Desk', 'access' => 'report-service-desk'],
        'report-daily-activities' => ['label' => 'Daily Activities', 'access' => 'report-daily-activities'],
        'report-work-logs' => ['label' => 'Work Logs', 'access' => 'report-work-logs'],
        'report-sla-ticket-aging' => ['label' => 'SLA & Ticket Aging', 'access' => 'report-sla-ticket-aging'],
        'report-workload' => ['label' => 'Workload', 'access' => 'report-workload'],
        'report-department-activity' => ['label' => 'Department Activity', 'access' => 'report-department-activity'],
        'report-export' => ['label' => 'Export Reports', 'access' => 'report-export'],
        'outgoing-letters' => ['label' => 'Outgoing Letters', 'access' => 'letters-outgoing'],
        'hr-kpmog-letters' => ['label' => 'HR KPMOG', 'access' => 'letters-hr-kpmog'],
        'hr-apca-letters' => ['label' => 'HR APCA', 'access' => 'letters-hr-apca'],
        'kpmog-project-letters' => ['label' => 'KPMOG Project / BD', 'access' => 'letters-kpmog-project-bd'],
        'ate-general-letters' => ['label' => 'APCA General Correspondence', 'access' => 'letters-ate-general'],
        'letter-profiles' => ['label' => 'Letter Profiles', 'access' => 'letters-profiles'],
        'document-types' => ['label' => 'Letter Types', 'access' => 'letters-document-types'],
        'document-numbering-templates' => ['label' => 'Letter Number Templates', 'access' => 'letters-numbering-templates'],
        'ltro-mttr-records' => ['label' => 'MTTR Records', 'access' => 'ltro-mttr-records'],
        'ltro-daily-reports' => ['label' => 'Daily Reports', 'access' => 'ltro-daily-reports'],
        'ltro-availability' => ['label' => 'Availability LTRO 1B', 'access' => 'ltro-availability'],
        'atk-dashboard' => ['label' => 'Office Supplies Dashboard', 'access' => 'atk-dashboard'],
        'atk-requests' => ['label' => 'Office Supply Requests', 'access' => 'atk-requests'],
        'atk-summary' => ['label' => 'Requirements Summary', 'access' => 'atk-summary'],
        'atk-items' => ['label' => 'Office Supply Catalog', 'access' => 'atk-items'],
        'atk-categories' => ['label' => 'Item Categories', 'access' => 'atk-categories'],
        'atk-department-balance' => ['label' => 'Department Stock Balances', 'access' => 'atk-department-balance'],
        'atk-units' => ['label' => 'Units of Measure', 'access' => 'atk-units'],
        'atk-usage' => ['label' => 'Office Supply Usage', 'access' => 'atk-usage'],
        'atk-department-movement' => ['label' => 'Department Stock Movements', 'access' => 'atk-department-movement'],
        'atk-stock-movement' => ['label' => 'Office Supply Stock Movements', 'access' => 'atk-stock-movement'],
        'atk-request-history' => ['label' => 'Request Audit', 'access' => 'atk-request-history'],
        'atk-reports' => ['label' => 'Reports & Exports', 'access' => 'atk-reports'],
        'meeting-calendar' => ['label' => 'Calendar', 'access' => 'meeting-calendar'],
        'meeting-bookings' => ['label' => 'Bookings', 'access' => 'meeting-bookings'],
        'meeting-rooms' => ['label' => 'Meeting Rooms', 'access' => 'meeting-rooms'],
        'vehicle-calendar' => ['label' => 'Calendar', 'access' => 'vehicle-calendar'],
        'vehicle-bookings' => ['label' => 'Bookings', 'access' => 'vehicle-bookings'],
        'vehicles' => ['label' => 'Vehicles', 'access' => 'vehicles'],
        'reminders' => ['label' => 'Reminders', 'access' => 'notifications'],
        'service-desk' => ['label' => 'Service Desk', 'access' => 'service-desk'],
        'legal-approval' => ['label' => 'Legal Approval', 'access' => 'legal-approval'],
        'service-desk-manage' => ['label' => 'Manage Service Desk', 'access' => 'service-desk-manage'],
        'service-desk-comments' => ['label' => 'Send comment / reply', 'access' => 'service-desk-comments'],
        'request-categories' => ['label' => 'Request Categories', 'access' => 'request-categories'],
        'work-logs' => ['label' => 'Work Logs', 'access' => 'daily-report'],
        'findings-view' => ['label' => 'View Due Diligence Findings', 'access' => 'findings-view'],
        'findings-manage' => ['label' => 'Manage Due Diligence Findings', 'access' => 'findings-manage'],
        'daily-activities' => ['label' => 'Daily Activities', 'access' => 'daily-activities'],
        'task-categories' => ['label' => 'Task Categories', 'access' => 'task-categories'],
    ];

    private const ACCESS_GROUPS = [
        'travel-spd-create' => ['travel.spd.create'],
        'travel-spd-view' => ['travel.spd.view'],
        'travel-spd-manage' => ['travel.spd.manage'],
        'travel-lpj-create' => ['travel.lpj.create'],
        'travel-lpj-view' => ['travel.lpj.view'],
        'travel-lpj-manage' => ['travel.lpj.manage'],
        'travel-approve' => ['travel.approve'],
        'travel-configure' => ['travel.configure'],
        'inventory-manage' => ['inventory.manage'],
        'inventory-view-all' => ['inventory.view-all'],
        'inventory-department-issue' => ['inventory.department-issue'],
        'scm-requests' => ['scm.request'],
        'scm-material-register' => ['scm.material-register'],
        'scm-process' => ['scm.process'],
        'ga-process' => ['ga.process', 'scm.request'],
        'scm-process-kpmog' => ['scm.process-kpmog'],
        'scm-process-apca' => ['scm.process-apca'],
        'scm-configure' => ['scm.configure'],
        'finance-payments' => ['finance.payments'],
        'finance-coa' => ['finance.coa.manage'],
        'finance-budget' => ['finance.budget.manage'],
        'it-devices-view' => ['it.devices.view'],
        'it-devices-manage' => ['it.devices.manage'],
        'it-inspections-view' => ['it.inspections.view'],
        'it-inspections-create' => ['it.inspections.create'],
        'it-inspections-manage' => ['it.inspections.manage'],
        'it-followups-manage' => ['it.follow-ups.manage'],
        'it-reports-view' => ['it.reports.view'],
        'it-reports-export' => ['it.reports.export'],
        'master-subject-categories' => ['master.subject-categories.manage'],
        'master-permit-kbli' => ['master.permit-kbli.manage'],
        'master-projects' => ['master.projects.manage'],
        'master-activity-categories' => ['master.activity-categories.manage'],
        'master-departments' => ['master.departments.manage'],
        'master-employees' => ['master.employees.manage'],
        'master-work-locations' => ['master.work-locations.manage'],
        'notifications' => [
            'reminders.view',
        ],
        'service-desk' => [
            'tickets.create',
            'tickets.view',
        ],
        'service-desk-manage' => ['tickets.manage'],
        'legal-approval' => ['legal-tasks.approve'],
        'service-desk-comments' => ['comments.create'],
        'findings-view' => ['findings.view'],
        'findings-manage' => ['findings.manage'],
        'report-export' => ['report.export'],
        'atk-request' => [
            'atk.request',
        ],
        'atk-management' => [
            'atk.request',
            'atk.manage',
            'atk.report',
        ],
        'atk-dashboard' => ['atk.dashboard'],
        'atk-requests' => ['atk.requests', 'atk.request'],
        'atk-summary' => ['atk.summary', 'atk.report'],
        'atk-items' => ['atk.items', 'atk.manage'],
        'atk-categories' => ['atk.categories', 'atk.manage'],
        'atk-department-balance' => ['atk.department-balance'],
        'atk-units' => ['atk.units', 'atk.manage'],
        'atk-usage' => ['atk.usage', 'atk.request'],
        'atk-department-movement' => ['atk.department-movement'],
        'atk-stock-movement' => ['atk.stock-movement', 'atk.manage'],
        'atk-request-history' => ['atk.request-history', 'atk.manage'],
        'atk-reports' => ['atk.reports', 'atk.report'],
        'daily-report' => [
            'worklogs.view',
            'worklogs.manage',
        ],
        'ltro' => [
            'ltro.view',
            'ltro.manage',
        ],
        'ltro-mttr-records' => ['ltro.mttr-records', 'ltro.view', 'ltro.manage'],
        'ltro-daily-reports' => ['ltro.daily-reports', 'ltro.view'],
        'ltro-availability' => ['ltro.availability', 'ltro.view'],
        'meeting-room' => [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
        ],
        'meeting-room-management' => [
            'meeting-bookings.view',
            'meeting-bookings.create',
            'meeting-bookings.cancel-own',
            'meeting-bookings.manage',
            'meeting-rooms.manage',
        ],
        'meeting-calendar' => ['meeting.calendar', 'meeting-bookings.view'],
        'meeting-bookings' => [
            'meeting.bookings', 'meeting-bookings.view', 'meeting-bookings.create',
            'meeting-bookings.cancel-own',
        ],
        'meeting-rooms' => [
            'meeting.rooms', 'meeting-bookings.view', 'meeting-bookings.create',
            'meeting-bookings.cancel-own', 'meeting-bookings.manage', 'meeting-rooms.manage',
        ],
        'vehicle-booking' => [
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
        ],
        'vehicle-booking-management' => [
            'vehicle-bookings.view',
            'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
            'vehicle-bookings.manage',
            'vehicles.manage',
        ],
        'vehicle-calendar' => ['vehicle.calendar', 'vehicle-bookings.view'],
        'vehicle-bookings' => [
            'vehicle.bookings', 'vehicle-bookings.view', 'vehicle-bookings.create',
            'vehicle-bookings.cancel-own',
        ],
        'vehicles' => [
            'vehicle.vehicles', 'vehicle-bookings.view', 'vehicle-bookings.create',
            'vehicle-bookings.cancel-own', 'vehicle-bookings.manage', 'vehicles.manage',
        ],
        'attendance-report' => [
            'attendance.view',
        ],
        'attendance-management' => [
            'attendance.view',
            'attendance.upload',
            'attendance.manage',
        ],
        'attendance-report-center' => ['attendance.report-center', 'attendance.view'],
        'attendance-upload-period' => ['attendance.upload-period', 'attendance.view', 'attendance.upload', 'attendance.manage'],
        'attendance-report-results' => ['attendance.report-results', 'attendance.view'],
        'report-overview' => ['report.view', 'report.overview'],
        'report-service-desk' => ['report.view', 'report.service-desk'],
        'report-daily-activities' => ['report.view', 'report.daily-activities'],
        'report-work-logs' => ['report.view', 'report.work-logs'],
        'report-sla-ticket-aging' => ['report.view', 'report.sla-ticket-aging'],
        'report-workload' => ['report.view', 'report.workload'],
        'report-department-activity' => ['report.view', 'report.department-activity'],
        'reports' => ['report.view', 'report.export'],
        'letters-outgoing' => ['letters.outgoing', 'letters.view', 'letters.create', 'letters.issue', 'letters.legacy-import'],
        'letters-hr-kpmog' => ['letters.hr-kpmog', 'letters.view', 'letters.create', 'letters.issue', 'letters.legacy-import'],
        'letters-hr-apca' => ['letters.hr-apca', 'letters.view', 'letters.create', 'letters.issue', 'letters.legacy-import'],
        'letters-kpmog-project-bd' => ['letters.kpmog-project-bd', 'letters.view', 'letters.create', 'letters.issue', 'letters.legacy-import'],
        'letters-ate-general' => ['letters.ate-general', 'letters.view', 'letters.create', 'letters.issue', 'letters.legacy-import'],
        'letters-profiles' => ['letters.profiles', 'letters.manage'],
        'letters-document-types' => ['letters.document-types', 'letters.manage'],
        'letters-numbering-templates' => ['letters.numbering-templates', 'letters.manage'],
        'master-data' => [
            'master-data.manage',
        ],
        'request-categories' => [
            'ticket-categories.manage',
        ],
        'daily-activities' => [
            'daily-activities.manage',
        ],
        'task-categories' => [
            'task-categories.manage',
        ],
    ];

    public function options(): array
    {
        return [
            'travel-spd-create' => 'Business Travel - Create your own SPD',
            'travel-spd-view' => 'Business Travel - View department SPD',
            'travel-spd-manage' => 'Business Travel - Manage department SPD drafts',
            'travel-lpj-create' => 'Business Travel - Create your own LPJ',
            'travel-lpj-view' => 'Business Travel - View department LPJ',
            'travel-lpj-manage' => 'Business Travel - Manage department LPJ drafts',
            'travel-approve' => 'Business Travel - Assigned approvals',
            'travel-configure' => 'Business Travel - Manage SPD/LPJ approval rules',
            'scm-requests' => 'Permintaan - PR dan SR sendiri',
            'scm-material-register' => 'Pengadaan - Rekap Material PR',
            'finance-payments' => 'Finance - PO Payments',
            'finance-coa' => 'Finance - Master COA',
            'finance-budget' => 'Finance - Budget Settings',
            'inventory-manage' => 'Inventory - Manage inventory and warehouse movements',
            'inventory-view-all' => 'Inventory - View all entities',
            'inventory-department-issue' => 'Inventory - Issue stock to departments',
            'scm-process' => 'SCM - Proses pengadaan',
            'ga-process' => 'Purchasing Officer - Proses HO KPMOG/APCA',
            'scm-process-kpmog' => 'SCM - Project KPMOG',
            'scm-process-apca' => 'SCM - Project APCA',
            'scm-configure' => 'SCM - Pengaturan master',
            'it-devices-view' => 'IT Infrastructure - View Device Catalog',
            'it-devices-manage' => 'IT Infrastructure - Manage Device Catalog',
            'it-inspections-view' => 'IT Infrastructure - View inspections',
            'it-inspections-create' => 'IT Infrastructure - Record inspections',
            'it-inspections-manage' => 'IT Infrastructure - Manage inspections',
            'it-followups-manage' => 'IT Infrastructure - Manage follow-ups',
            'it-reports-view' => 'IT Infrastructure - View summary and reports',
            'it-reports-export' => 'IT Infrastructure - Export reports to Excel',
            'master-data' => 'Manage all Master Data',
            'master-departments' => 'Master Data - Manage Departments',
            'master-employees' => 'Master Data - Manage Employees',
            'master-work-locations' => 'Attendance Report - Manage Work Locations',
            'service-desk-manage' => 'Manage Service Desk',
            'legal-approval' => 'Legal Approval',
            'service-desk-comments' => 'Comment on Service Desk tickets',
            'findings-view' => 'View Due Diligence Findings',
            'findings-manage' => 'Manage Due Diligence Findings',
            'report-export' => 'Export Reports',
            'attendance-report' => 'View Attendance Report and Report Results',
            'attendance-management' => 'Attendance Reports - Upload and manage data',
            'reports' => 'Access all Reports and Exports',
            'letters-profiles' => 'Correspondence - Manage letter profiles',
            'letters-outgoing' => 'Outgoing Letters - Create and manage drafts',
            'letters-document-types' => 'Correspondence - Manage letter types',
            'letters-numbering-templates' => 'Correspondence - Manage letter number templates',
            'ltro' => 'Access all LTRO menus',
            'atk-request' => 'Office Supplies - Requests and department stock',
            'atk-management' => 'Office Supplies - Manage main warehouse, catalog, and reports',
            'daily-report' => 'Work Logs',
            'meeting-room' => 'Meeting Room - My bookings',
            'meeting-room-management' => 'Meeting Room - Manage bookings and rooms',
            'vehicle-booking' => 'Vehicle Booking - My bookings',
            'vehicle-booking-management' => 'Vehicle Booking - Manage bookings and vehicles',
            'notifications' => 'Reminders',
            'service-desk' => 'Create and view requests',
            'request-categories' => 'Request Categories',
            'daily-activities' => 'Daily Activities',
            'task-categories' => 'Task Categories',
        ];
    }

    public function menuDescriptions(): array
    {
        return [
            'notifications' => 'Reminders.',
            'service-desk' => 'Service Desk.',
            'legal-approval' => 'Approving or refusing to submit Service Desk to Legal via Work Logs.',
            'master-data' => 'Subject Categories, Permit & KBLI, Projects, Activity Categories.',
            'attendance-report' => 'Attendance Report and Report Results.',
            'attendance-management' => 'Upload Period and manage the attendance data.',
            'reports' => 'Overview, Service Desk, Daily Activities, Work Logs, SLA & Ticket Aging, Workload, Department Activity.',
            'letters-outgoing' => 'A list of outgoing mail and new mail drafts.',
            'letters-hr-kpmog' => 'HR KPMOG register.',
            'letters-hr-apca' => 'HR APCA register.',
            'letters-kpmog-project-bd' => 'KPMOG Project and BD mail registers.',
            'letters-ate-general' => 'Register general APCA mail.',
            'letters-document-types' => 'The type of mail available for outgoing mail.',
            'letters-profiles' => 'Mail groups for every entity, department, and charging plot.',
            'letters-numbering-templates' => 'Template mail numbers based on company, department, and mail types.',
            'ltro' => 'MTTR Records, Daily Reports, Availability LTRO 1B.',
            'atk-request' => 'Office supplies request, Department stock suit department, and office supplies use.',
            'atk-management' => 'Dashboard, Recap, Master of office supplies goods, Category, Units, Main Storage Mutations, Audits and Reports.',
            'meeting-room' => 'Calendar and Bookings.',
            'meeting-room-management' => 'Meeting Rooms and managing the entire booking.',
            'vehicle-booking' => 'Calendar and Bookings.',
            'vehicle-booking-management' => 'Vehicles and the management of the entire booking.',
            'daily-report' => 'The ability to manage Work Logs.',
            'request-categories' => 'Request Categories to Service Desk.',
            'daily-activities' => 'Daily Activities on the Daily Reports.',
            'task-categories' => 'Task Categories at Daily Reports.',
        ];
    }

    public function menuGroups(): array
    {
        return [
            'travel' => ['label' => 'Business Travel', 'description' => 'Access to SPD and LPJ follows company and department permissions. Each LPJ links to an approved SPD. Assigned reviewers can approve documents.',
                'items' => ['travel-spd-create', 'travel-spd-view', 'travel-spd-manage', 'travel-lpj-create', 'travel-lpj-view', 'travel-lpj-manage', 'travel-approve', 'travel-configure']],
            'inventory' => ['label' => 'Inventory', 'description' => 'The master of goods, the Ho/Project warehouse, stock, PR acceptance and assets. View all entities gives a combined display without adding power to data change.', 'items' => ['inventory-manage', 'inventory-view-all', 'inventory-department-issue']],
            'procurement_requests' => ['label' => 'Permintaan', 'description' => 'Akses PR/SR juga menampilkan alokasi anggaran departemen yang dapat diakses.', 'items' => ['scm-requests', 'department-budgets']],
            'scm' => ['label' => 'SCM', 'description' => 'Memproses PR/SR Project sesuai entitas dan akses departemen. Izin SCM lama juga membuka Purchasing Officer untuk HO.', 'items' => ['scm-material-register', 'scm-process-kpmog', 'scm-process-apca', 'scm-process', 'scm-configure']],
            'purchasing_officer' => ['label' => 'Purchasing Officer', 'description' => 'Memproses PR/SR HO KPMOG/APCA sesuai akses departemen melalui menu Purchasing Officer.', 'items' => ['ga-process']],
            'finance' => ['label' => 'Finance', 'description' => 'Record PO payments and manage the chart of accounts and budgets within your permissions.', 'items' => ['finance-payments', 'finance-coa', 'finance-budget']],
            'approvals' => [
                'label' => 'Approval Authority',
                'description' => 'Check for CBO who approves Service Desk application to Legal, work Logs and decision history are automatically available.',
                'items' => ['legal-approval'],
                'field_label' => 'Approval',
                'bulk_toggleable' => false,
            ],
            'it_infrastructure' => [
                'label' => 'IT Infrastructure',
                'description' => 'Select the ability of an IT officer. The scope of data continues to follow user accessible management department.',
                'items' => ['it-devices-view', 'it-devices-manage', 'it-inspections-view', 'it-inspections-create', 'it-inspections-manage', 'it-followups-manage', 'it-reports-view', 'it-reports-export'],
            ],
            'master_data' => [
                'label' => 'Master Data',
                'description' => 'Select the required Master Data menu.',
                'items' => ['departments', 'employees', 'subject-categories', 'permit-kbli', 'projects', 'activity-categories'],
            ],
            'attendance_report' => [
                'label' => 'Attendance Report',
                'description' => 'Choose access to the report or manage the attendance data.',
                'items' => ['attendance-report-center', 'attendance-upload-period', 'attendance-report-results', 'work-locations'],
            ],
            'reports' => [
                'label' => 'Reports',
                'description' => 'Select a report that can be opened. Export Reports allows export reports that users can access.',
                'items' => ['report-overview', 'report-service-desk', 'report-daily-activities', 'report-work-logs', 'report-sla-ticket-aging', 'report-workload', 'report-department-activity', 'report-export'],
            ],
            'letters' => [
                'label' => 'Correspondence',
                'description' => 'Mail out and setting mail number.',
                'items' => ['outgoing-letters', 'hr-kpmog-letters', 'hr-apca-letters', 'kpmog-project-letters', 'ate-general-letters', 'letter-profiles', 'document-types', 'document-numbering-templates'],
            ],
            'ltro' => [
                'label' => 'LTRO Management',
                'description' => 'MTTR Records, Daily Reports, and Availability LTRO 1B.',
                'items' => ['ltro-mttr-records', 'ltro-daily-reports', 'ltro-availability'],
            ],
            'atk' => [
                'label' => 'Office Supplies',
                'description' => 'Choose request access or office supplies stock management.',
                'items' => ['atk-dashboard', 'atk-requests', 'atk-summary', 'atk-items', 'atk-categories', 'atk-department-balance', 'atk-units', 'atk-usage', 'atk-department-movement', 'atk-stock-movement', 'atk-request-history', 'atk-reports'],
            ],
            'meeting_room' => [
                'label' => 'Meeting Room',
                'description' => 'Choose a private booking or access to space management.',
                'items' => ['meeting-calendar', 'meeting-bookings', 'meeting-rooms'],
            ],
            'vehicle_booking' => [
                'label' => 'Vehicle Booking',
                'description' => 'Choose a private booking or access to vehicle management.',
                'items' => ['vehicle-calendar', 'vehicle-bookings', 'vehicles'],
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Personal reminder. Request notification follows source permission and destination department.',
                'items' => ['reminders'],
            ],
            'service_desk' => [
                'label' => 'Service Desk',
                'description' => 'Service Desk gave us access to make and see the demand, including HR manpower.',
                'items' => ['service-desk', 'service-desk-manage', 'service-desk-comments', 'request-categories'],
            ],
            'daily_reports' => [
                'label' => 'Daily Reports',
                'description' => 'Findings are available in Work Logs for Due Diligence request. Select Work Logs for users to open details of their findings.',
                'items' => ['work-logs', 'findings-view', 'findings-manage', 'daily-activities', 'task-categories'],
            ],
        ];
    }

    public function optionsForGroups(array $groups): array
    {
        return array_intersect_key($this->options(), array_flip($groups));
    }

    public function menuOptionsForModule(array $module): array
    {
        return collect($module['items'])
            ->mapWithKeys(fn (string $item): array => [$item => self::MENU_ITEMS[$item]['label']])
            ->all();
    }

    public function optionsForLevel(string $level): array
    {
        return $this->options();
    }

    public function validateForLevel(string $level, array $accessGroups): array
    {
        $selectedGroups = collect($accessGroups)
            ->filter(fn ($group): bool => is_string($group))
            ->unique()
            ->values()
            ->all();
        $allowedGroups = array_keys(self::ACCESS_GROUPS);

        if (array_diff($selectedGroups, $allowedGroups) !== []) {
            abort(422, 'Invalid menu access option.');
        }

        return $selectedGroups;
    }

    public function stateFor(User $user): array
    {
        $directCodes = $user->directPermissions()
            ->pluck('code')
            ->all();

        return collect(self::ACCESS_GROUPS)
            ->filter(
                fn (array $permissionCodes): bool => collect($permissionCodes)
                    ->every(fn (string $code): bool => in_array($code, $directCodes, true))
            )
            ->keys()
            ->all();
    }

    public function menuStateFor(User $user): array
    {
        $selectedGroups = $this->stateFor($user);

        if (in_array('master-data', $selectedGroups, true)) {
            $selectedGroups = [
                ...$selectedGroups,
                'master-subject-categories',
                'master-permit-kbli',
                'master-projects',
                'master-activity-categories',
                'master-departments',
                'master-employees',
                'master-work-locations',
                'request-categories',
                'task-categories',
            ];
        }

        return collect($this->menuGroups())
            ->mapWithKeys(fn (array $module, string $key): array => [
                $key => collect($module['items'])
                    ->filter(fn (string $item): bool => in_array(self::MENU_ITEMS[$item]['access'], $selectedGroups, true))
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    public function accessGroupsFromMenuState(array $menuAccess): array
    {
        return collect($menuAccess)
            ->flatten()
            ->filter(fn ($item): bool => is_string($item) && array_key_exists($item, self::MENU_ITEMS))
            ->map(fn (string $item): string => self::MENU_ITEMS[$item]['access'])
            ->unique()
            ->values()
            ->all();
    }

    public function sync(User $user, array $accessGroups): void
    {
        $selectedGroups = collect($accessGroups)
            ->filter(fn ($group): bool => array_key_exists($group, self::ACCESS_GROUPS))
            ->unique();
        $managedCodes = collect(self::ACCESS_GROUPS)->flatten()->unique();
        $selectedCodes = $selectedGroups
            ->flatMap(fn (string $group): array => self::ACCESS_GROUPS[$group])
            ->unique();
        $managedIds = Permission::query()
            ->whereIn('code', $managedCodes)
            ->pluck('id');
        $selectedIds = Permission::query()
            ->whereIn('code', $selectedCodes)
            ->pluck('id');

        $user->directPermissions()->detach($managedIds);
        $user->directPermissions()->attach($selectedIds);
        $user->unsetRelation('directPermissions');
    }
}
