# Data Model

Daftar model Eloquent di `app/Models` beserta tabel dan relasi yang dideklarasikan, dikelompokkan per domain. Dihasilkan dari kode pada 27 Oktober 2026 (163 model; bagian Kitchen diperbarui dari refleksi relasi dengan format yang sama: tiga model snapshot Production Order ESB ditambahkan dan dua model rencana lokal dihapus pada PRD v3.0). Struktur kolom lengkap ada di `database/migrations`; untuk melihat skema aktual gunakan `php artisan db:table {tabel}` atau tool `database-schema` Laravel Boost.

Kolom *Relasi* hanya memuat relasi yang dideklarasikan dengan return type eksplisit (`metode→Model (Tipe)`).

## Platform & Master Data

| Model | Tabel | Relasi |
|---|---|---|
| `AppSetting` | `app_settings` | — |
| `Branch` | `branches` | esbCodes→BranchEsbCode (HasMany), stockCardEsbCode→BranchEsbCode (BelongsTo), brand→Brand (BelongsTo), users→User (HasMany), supervisors→User (BelongsToMany), openings→CasualPositionOpening (HasMany), clockRecords→CasualClockRecord (HasMany), briefingTasks→BriefingTask (HasMany), briefingBranchSettings→BriefingBranchSetting (HasMany), qualityControlAudits→QualityControlAudit (HasMany), salesReports→SalesReport (HasMany), salesProjectionTargets→RndProductSalesProjection (BelongsToMany), salesShifts→BranchSalesShift (HasMany), activeSalesShifts→BranchSalesShift (HasMany), employees→Employee (HasMany) |
| `BranchEsbCode` | `branch_esb_codes` | branch→Branch (BelongsTo) |
| `BranchSalesShift` | `branch_sales_shifts` | branch→Branch (BelongsTo), basketSizeRecords→BasketSizeRecord (HasMany) |
| `Brand` | `brands` | branches→Branch (HasMany) |
| `Employee` | `employees` | branch→Branch (BelongsTo), salesReports→SalesReport (HasManyThrough) |
| `EsbPaymentMethodCache` | `esb_payment_method_cache` | — |
| `NotificationDelivery` | `notification_deliveries` | — |
| `SalesRegion` | `sales_regions` | productPrices→RndProductRegionalPrice (HasMany), productSalesProjections→RndProductSalesProjection (HasMany) |
| `User` | `users` | branch→Branch (BelongsTo), supervisedBranches→Branch (BelongsToMany), casualPosition→CasualPosition (BelongsTo), casualPositionRegistration→CasualPositionRegistration (HasOne), casualPositionRegistrations→CasualPositionRegistration (HasMany), upcomingRegistrations→CasualPositionRegistration (HasMany), projectTaskAssignments→RndProjectTaskAssignment (HasMany) |
| `WhatsappNotificationSetting` | `whatsapp_notification_settings` | pic→User (BelongsTo) |

## Workforce (Casual Staff)

| Model | Tabel | Relasi |
|---|---|---|
| `CasualClockRecord` | `casual_clock_records` | user→User (BelongsTo), branch→Branch (BelongsTo), overtimeRequest→CasualOvertimeRequest (HasOne) |
| `CasualOvertimeRequest` | `casual_overtime_requests` | clockRecord→CasualClockRecord (BelongsTo), user→User (BelongsTo), reviewedBy→User (BelongsTo) |
| `CasualPosition` | `casual_positions` | users→User (HasMany), openings→CasualPositionOpening (HasMany) |
| `CasualPositionOpening` | `casual_position_openings` | casualPosition→CasualPosition (BelongsTo), branch→Branch (BelongsTo), postedBy→User (BelongsTo), registrations→CasualPositionRegistration (HasMany) |
| `CasualPositionRegistration` | `casual_position_registrations` | opening→CasualPositionOpening (BelongsTo), user→User (BelongsTo) |

## Daily Briefing

| Model | Tabel | Relasi |
|---|---|---|
| `BriefingBranchSetting` | `briefing_branch_settings` | branch→Branch (BelongsTo), createdBy→User (BelongsTo), updatedBy→User (BelongsTo) |
| `BriefingItem` | `briefing_items` | record→BriefingRecord (BelongsTo), reviewer→User (BelongsTo), reopener→User (BelongsTo), briefingTask→BriefingTask (BelongsTo) |
| `BriefingPeriodWeight` | `briefing_period_weights` | branch→Branch (BelongsTo) |
| `BriefingRecord` | `briefing_records` | user→User (BelongsTo), branch→Branch (BelongsTo), items→BriefingItem (HasMany) |
| `BriefingScore` | `briefing_scores` | branch→Branch (BelongsTo) |
| `BriefingSettings` | `briefing_settings` | — |
| `BriefingTask` | `briefing_tasks` | branch→Branch (BelongsTo) |

## Driver

| Model | Tabel | Relasi |
|---|---|---|
| `DriverMealAllowancePeriod` | `driver_meal_allowance_periods` | summaries→DriverMealAllowanceSummary (HasMany), items→DriverMealAllowanceTripItem (HasMany), finalizedBy→User (BelongsTo) |
| `DriverMealAllowanceSummary` | `driver_meal_allowance_summaries` | period→DriverMealAllowancePeriod (BelongsTo), driver→User (BelongsTo), items→DriverMealAllowanceTripItem (HasMany) |
| `DriverMealAllowanceTripItem` | `driver_meal_allowance_trip_items` | summary→DriverMealAllowanceSummary (BelongsTo), trip→Trip (BelongsTo) |
| `DriverTripSettings` | `driver_trip_settings` | — |
| `FuelType` | `fuel_types` | — |
| `Trip` | `trips` | driver→User (BelongsTo), vehicle→Vehicle (BelongsTo), tripRoute→TripRoute (BelongsTo), waypointCheckins→TripWaypointCheckin (HasMany), fuelFillup→TripFuelFillup (HasOne) |
| `TripFuelFillup` | `trip_fuel_fillups` | trip→Trip (BelongsTo) |
| `TripRoute` | `trip_routes` | waypoints→TripRouteWaypoint (HasMany), trips→Trip (HasMany), createdByDriver→User (BelongsTo) |
| `TripRouteWaypoint` | `trip_route_waypoints` | tripRoute→TripRoute (BelongsTo), checkins→TripWaypointCheckin (HasMany) |
| `TripWaypointCheckin` | `trip_waypoint_checkins` | trip→Trip (BelongsTo), waypoint→TripRouteWaypoint (BelongsTo) |
| `Vehicle` | `vehicles` | — |

## Technician

| Model | Tabel | Relasi |
|---|---|---|
| `Asset` | `assets` | branch→Branch (BelongsTo), serviceRequests→ServiceRequest (HasMany), completedRepairs→ServiceRequestRepair (HasManyThrough) |
| `ServiceRequest` | `service_requests` | technician→User (BelongsTo), asset→Asset (BelongsTo), branch→Branch (BelongsTo), scheduledBy→User (BelongsTo), repairs→ServiceRequestRepair (HasMany), activeRepair→ServiceRequestRepair (HasOne) |
| `ServiceRequestRepair` | `service_request_repairs` | serviceRequest→ServiceRequest (BelongsTo), technician→User (BelongsTo) |
| `TechnicianMaintenance` | `technician_maintenances` | branch→Branch (BelongsTo), technician→User (BelongsTo), reviewer→User (BelongsTo), items→TechnicianMaintenanceItem (HasMany) |
| `TechnicianMaintenanceChecklist` | `technician_maintenance_checklists` | maintenanceItems→TechnicianMaintenanceItem (HasMany) |
| `TechnicianMaintenanceItem` | `technician_maintenance_items` | maintenance→TechnicianMaintenance (BelongsTo), checklist→TechnicianMaintenanceChecklist (BelongsTo) |
| `TechnicianMonthlyMaintenance` | `technician_monthly_maintenances` | — |
| `TechnicianSettings` | `technician_settings` | — |

## Information Technology

| Model | Tabel | Relasi |
|---|---|---|
| `BulkProductSubmission` | `bulk_product_submissions` | creator→User (BelongsTo), items→BulkProductSubmissionItem (HasMany) |
| `BulkProductSubmissionItem` | `bulk_product_submission_items` | submission→BulkProductSubmission (BelongsTo) |
| `ErpModule` | `erp_modules` | erpRepairRequests→ErpRepairRequest (HasMany) |
| `ErpRepairRequest` | `erp_repair_requests` | requester→User (BelongsTo), branch→Branch (BelongsTo), module→ErpModule (BelongsTo), requestType→ItRequestType (BelongsTo), closedBy→User (BelongsTo), activities→ItRequestActivity (HasMany) |
| `ItRequestActivity` | `it_request_activities` | request→ErpRepairRequest (BelongsTo), actor→User (BelongsTo) |
| `ItRequestType` | `it_request_types` | requests→ErpRepairRequest (HasMany) |

## System Monitoring

| Model | Tabel | Relasi |
|---|---|---|
| `MonitoringAlert` | `monitoring_alerts` | service→MonitoringService (BelongsTo), incident→MonitoringIncident (BelongsTo), acknowledgedBy→User (BelongsTo) |
| `MonitoringCheckResult` | `monitoring_check_results` | service→MonitoringService (BelongsTo) |
| `MonitoringDailyAggregate` | `monitoring_daily_aggregates` | — |
| `MonitoringDeployment` | `monitoring_deployments` | — |
| `MonitoringError` | `monitoring_errors` | triagedBy→User (BelongsTo) |
| `MonitoringHeartbeat` | `monitoring_heartbeats` | — |
| `MonitoringIncident` | `monitoring_incidents` | owner→User (BelongsTo), creator→User (BelongsTo), erpRepairRequest→ErpRepairRequest (BelongsTo), events→MonitoringIncidentEvent (HasMany), links→MonitoringIncidentLink (HasMany), alerts→MonitoringAlert (HasMany) |
| `MonitoringIncidentEvent` | `monitoring_incident_events` | incident→MonitoringIncident (BelongsTo), actor→User (BelongsTo) |
| `MonitoringIncidentLink` | `monitoring_incident_links` | incident→MonitoringIncident (BelongsTo) |
| `MonitoringScheduledTaskRun` | `monitoring_scheduled_task_runs` | — |
| `MonitoringService` | `monitoring_services` | checkResults→MonitoringCheckResult (HasMany), activeAlert→MonitoringAlert (HasOne) |
| `MonitoringSetting` | `monitoring_settings` | updatedBy→User (BelongsTo) |
| `MonitoringTransaction` | `monitoring_transactions` | parent→self (BelongsTo), attempts→self (HasMany), actor→User (BelongsTo), reconciledBy→User (BelongsTo), branch→Branch (BelongsTo) |

## Operational

| Model | Tabel | Relasi |
|---|---|---|
| `CustomerComplaint` | `customer_complaints` | branch→Branch (BelongsTo), submitter→User (BelongsTo) |
| `StoreSalesOrder` | `store_sales_orders` | branch→Branch (BelongsTo), branchEsbCode→BranchEsbCode (BelongsTo), items→StoreSalesOrderItem (HasMany), activities→StoreSalesOrderActivity (HasMany), submitter→User (BelongsTo), updater→User (BelongsTo) |
| `StoreSalesOrderActivity` | `store_sales_order_activities` | order→StoreSalesOrder (BelongsTo), creator→User (BelongsTo) |
| `StoreSalesOrderItem` | `store_sales_order_items` | order→StoreSalesOrder (BelongsTo) |
| `StoreSop` | `store_sops` | branches→Branch (BelongsToMany), brand→Brand (BelongsTo), category→StoreSopCategory (BelongsTo), sourceProject→RndProject (BelongsTo), sourceProduct→RndProjectProduct (BelongsTo), assignments→StoreSopAssignment (HasMany), creator→User (BelongsTo), publisher→User (BelongsTo) |
| `StoreSopAssignment` | `store_sop_assignments` | sop→StoreSop (BelongsTo), branch→Branch (BelongsTo), user→User (BelongsTo) |
| `StoreSopCategory` | `store_sop_categories` | storeSops→StoreSop (HasMany) |

## Research & Development

| Model | Tabel | Relasi |
|---|---|---|
| `MaterialSourcing` | `material_sourcings` | material→RndProductEsbMaterial (BelongsTo), submittedBy→User (BelongsTo) |
| `MaterialSourcingApproval` | `material_sourcing_approvals` | material→RndProductEsbMaterial (BelongsTo), actor→User (BelongsTo) |
| `PrefixCategory` | `prefix_categories` | prefixNames→PrefixName (BelongsToMany) |
| `PrefixName` | `prefix_names` | prefixCategories→PrefixCategory (BelongsToMany) |
| `ProductPriceSnapshot` | `product_price_snapshots` | — |
| `ProductPriceSyncRun` | `product_price_sync_runs` | — |
| `RndBomCatalog` | `rnd_bom_catalogs` | — |
| `RndBomChangeLog` | `rnd_bom_change_logs` | changedBy→User (BelongsTo), reconciledBy→User (BelongsTo) |
| `RndBomDocumentMaterial` | `rnd_bom_document_materials` | bom→RndProjectBom (BelongsTo) |
| `RndBomInstruction` | `rnd_bom_instructions` | project→RndProject (BelongsTo), product→RndProjectProduct (BelongsTo), updater→User (BelongsTo) |
| `RndInternalMemo` | `rnd_internal_memos` | menus→RndInternalMemoMenu (HasMany), brand→Brand (BelongsTo), branches→RndInternalMemoBranch (HasMany), extraProducts→RndInternalMemoExtraProduct (HasMany), documents→RndInternalMemoDocument (HasMany), syncRuns→RndInternalMemoSyncRun (HasMany), creator→User (BelongsTo), updater→User (BelongsTo), finalizer→User (BelongsTo), archiver→User (BelongsTo) |
| `RndInternalMemoBranch` | `rnd_internal_memo_branches` | memo→RndInternalMemo (BelongsTo), branch→Branch (BelongsTo), branchEsbCode→BranchEsbCode (BelongsTo), menus→RndInternalMemoMenu (BelongsToMany) |
| `RndInternalMemoCatalogSync` | `rnd_internal_memo_catalog_syncs` | triggeredBy→User (BelongsTo) |
| `RndInternalMemoDocument` | `rnd_internal_memo_documents` | memo→RndInternalMemo (BelongsTo), generator→User (BelongsTo) |
| `RndInternalMemoExtraProduct` | `rnd_internal_memo_extra_products` | memo→RndInternalMemo (BelongsTo), creator→User (BelongsTo) |
| `RndInternalMemoMaterial` | `rnd_internal_memo_materials` | menu→RndInternalMemoMenu (BelongsTo), parent→self (BelongsTo), children→self (HasMany) |
| `RndInternalMemoMenu` | `rnd_internal_memo_menus` | memo→RndInternalMemo (BelongsTo), materials→RndInternalMemoMaterial (HasMany), branches→RndInternalMemoBranch (BelongsToMany) |
| `RndInternalMemoMenuCatalog` | `rnd_internal_memo_menu_catalogs` | — |
| `RndInternalMemoSyncRun` | `rnd_internal_memo_sync_runs` | memo→RndInternalMemo (BelongsTo), triggeredBy→User (BelongsTo) |
| `RndMaterialAuditLog` | `rnd_material_audit_logs` | material→RndProductEsbMaterial (BelongsTo), actor→User (BelongsTo) |
| `RndMaterialPricingRequest` | `rnd_material_pricing_requests` | material→RndProductEsbMaterial (BelongsTo), sampleCycle→RndMaterialSampleCycle (BelongsTo), supplierCandidate→MaterialSourcing (BelongsTo), drafter→User (BelongsTo), verifier→User (BelongsTo) |
| `RndMaterialReview` | `rnd_material_reviews` | material→RndProductEsbMaterial (BelongsTo), submitter→User (BelongsTo), claimer→User (BelongsTo), reviewer→User (BelongsTo) |
| `RndMaterialSampleAttachment` | `rnd_material_sample_attachments` | cycle→RndMaterialSampleCycle (BelongsTo), uploader→User (BelongsTo) |
| `RndMaterialSampleCycle` | `rnd_material_sample_cycles` | material→RndProductEsbMaterial (BelongsTo), supplierCandidate→MaterialSourcing (BelongsTo), attachments→RndMaterialSampleAttachment (HasMany), handedOverBy→User (BelongsTo), receivedBy→User (BelongsTo), evaluatedBy→User (BelongsTo) |
| `RndProductEsbMaterial` | `rnd_product_esb_materials` | product→RndProjectProduct (BelongsTo), creator→User (BelongsTo), units→RndProductEsbMaterialUnit (HasMany), sourcings→MaterialSourcing (HasMany), selectedSourcing→MaterialSourcing (BelongsTo), reviews→RndMaterialReview (HasMany), latestReview→RndMaterialReview (HasOne), auditLogs→RndMaterialAuditLog (HasMany), sampleCycles→RndMaterialSampleCycle (HasMany), pricingRequests→RndMaterialPricingRequest (HasMany), sourcingApprovals→MaterialSourcingApproval (HasMany), rndReviewer→User (BelongsTo), financeReviewer→User (BelongsTo) |
| `RndProductEsbMaterialUnit` | `rnd_product_esb_material_units` | material→RndProductEsbMaterial (BelongsTo) |
| `RndProductEsbShelfLife` | `rnd_esb_product_shelf_lives` | creator→User (BelongsTo), updater→User (BelongsTo) |
| `RndProductRegionalPrice` | `rnd_product_regional_prices` | product→RndProjectProduct (BelongsTo), region→SalesRegion (BelongsTo) |
| `RndProductSalesProjection` | `rnd_product_sales_projections` | product→RndProjectProduct (BelongsTo), region→SalesRegion (BelongsTo), creator→User (BelongsTo), targetBranches→Branch (BelongsToMany) |
| `RndProject` | `rnd_projects` | boms→RndProjectBom (HasMany), products→RndProjectProduct (HasMany), partnershipMenus→RndProjectPartnershipMenu (HasMany), partnershipBranches→Branch (BelongsToMany), partnershipEquipment→RndProjectPartnershipEquipment (HasMany), premixAssemblies→RndProjectPremixAssembly (HasMany), documents→RndProjectDocument (HasMany), tasks→RndProjectTask (HasMany), taskTemplateApplications→RndProjectTaskTemplateApplication (HasMany), creator→User (BelongsTo) |
| `RndProjectBom` | `rnd_project_boms` | project→RndProject (BelongsTo), creator→User (BelongsTo), products→RndProjectProduct (BelongsToMany), documentMaterials→RndBomDocumentMaterial (HasMany) |
| `RndProjectDocument` | `rnd_project_documents` | project→RndProject (BelongsTo), creator→User (BelongsTo) |
| `RndProjectMarketingMaterial` | `rnd_project_marketing_materials` | product→RndProjectProduct (BelongsTo), creator→User (BelongsTo), fulfillment→MarketingMaterialFulfillment (HasOne) |
| `RndProjectPartnershipEquipment` | `rnd_project_partnership_equipment` | project→RndProject (BelongsTo), creator→User (BelongsTo) |
| `RndProjectPartnershipMenu` | `rnd_project_partnership_menus` | project→RndProject (BelongsTo), creator→User (BelongsTo) |
| `RndProjectPremixAssembly` | `rnd_project_premix_assemblies` | project→RndProject (BelongsTo), creator→User (BelongsTo) |
| `RndProjectProduct` | `rnd_project_products` | project→RndProject (BelongsTo), boms→RndProjectBom (BelongsToMany), creator→User (BelongsTo), marketingMaterials→RndProjectMarketingMaterial (HasMany), esbMaterials→RndProductEsbMaterial (HasMany), regionalPrices→RndProductRegionalPrice (HasMany), currentRegionalPrices→RndProductRegionalPrice (HasMany), salesProjections→RndProductSalesProjection (HasMany) |
| `RndProjectTask` | `rnd_project_tasks` | project→RndProject (BelongsTo), branches→Branch (BelongsToMany), assignments→RndProjectTaskAssignment (HasMany), creator→User (BelongsTo), completedBy→User (BelongsTo), templateApplication→RndProjectTaskTemplateApplication (BelongsTo), templateCheckpoint→RndProjectTaskTemplateCheckpoint (BelongsTo), copiedFromTask→self (BelongsTo) |
| `RndProjectTaskAssignment` | `rnd_project_task_assignments` | task→RndProjectTask (BelongsTo), branch→Branch (BelongsTo), user→User (BelongsTo), reviewedBy→User (BelongsTo), followUps→RndProjectTaskFollowUp (HasMany), reminders→RndProjectTaskReminder (HasMany) |
| `RndProjectTaskFollowUp` | `rnd_project_task_follow_ups` | assignment→RndProjectTaskAssignment (BelongsTo), submittedBy→User (BelongsTo) |
| `RndProjectTaskReminder` | `rnd_project_task_reminders` | assignment→RndProjectTaskAssignment (BelongsTo) |
| `RndProjectTaskTemplate` | `rnd_project_task_templates` | checkpoints→RndProjectTaskTemplateCheckpoint (HasMany), applications→RndProjectTaskTemplateApplication (HasMany), creator→User (BelongsTo) |
| `RndProjectTaskTemplateApplication` | `rnd_project_task_template_applications` | project→RndProject (BelongsTo), template→RndProjectTaskTemplate (BelongsTo), tasks→RndProjectTask (HasMany), appliedBy→User (BelongsTo) |
| `RndProjectTaskTemplateCheckpoint` | `rnd_project_task_template_checkpoints` | template→RndProjectTaskTemplate (BelongsTo), branches→Branch (BelongsToMany), picUsers→User (BelongsToMany) |
| `RndWipProduct` | `rnd_wip_products` | wipShelfLife→RndProductEsbShelfLife (HasOne) |

## Kitchen

| Model | Tabel | Relasi |
|---|---|---|
| `EsbProductionOrder` | `esb_production_orders` | branch→Branch (BelongsTo), mapping→BranchEsbCode (BelongsTo), details→EsbProductionOrderDetail (HasMany) |
| `EsbProductionOrderDetail` | `esb_production_order_details` | order→EsbProductionOrder (BelongsTo) |
| `EsbProductionOrderSyncRun` | `esb_production_order_sync_runs` | mapping→BranchEsbCode (BelongsTo), branch→Branch (BelongsTo) |

## Purchasing

| Model | Tabel | Relasi |
|---|---|---|
| `EsbPurchaseOrder` | `esb_purchase_orders` | items→EsbPurchaseOrderItem (HasMany) |
| `EsbPurchaseOrderItem` | `esb_purchase_order_items` | purchaseOrder→EsbPurchaseOrder (BelongsTo) |
| `MarketingMaterialFulfillment` | `marketing_material_fulfillments` | material→RndProjectMarketingMaterial (BelongsTo), orderedBy→User (BelongsTo), receivedBy→User (BelongsTo), location→Location (BelongsTo) |
| `PurchaseRequest` | `purchase_requests` | user→User (BelongsTo), branch→Branch (BelongsTo), processedBy→User (BelongsTo), statusHistories→PurchaseRequestStatusHistory (HasMany) |
| `PurchaseRequestStatusHistory` | `purchase_request_status_histories` | purchaseRequest→PurchaseRequest (BelongsTo), changedBy→User (BelongsTo) |
| `PurchasingRequest` | `purchasing_requests` | requester→User (BelongsTo), approvedBy→User (BelongsTo), items→PurchasingRequestItem (HasMany) |
| `PurchasingRequestItem` | `purchasing_request_items` | purchasingRequest→PurchasingRequest (BelongsTo) |
| `VendorComplianceIncident` | `vendor_compliance_incidents` | goodsReceipt→GoodsReceipt (BelongsTo), item→GoodsReceiptItem (BelongsTo), reportedBy→User (BelongsTo), handledBy→User (BelongsTo) |

## Quality Control

| Model | Tabel | Relasi |
|---|---|---|
| `QualityControlAudit` | `quality_control_audits` | branch→Branch (BelongsTo), auditor→User (BelongsTo), items→QualityControlAuditItem (HasMany) |
| `QualityControlAuditItem` | `quality_control_audit_items` | audit→QualityControlAudit (BelongsTo), checklistItem→QualityControlChecklistItem (BelongsTo), actionPic→User (BelongsTo) |
| `QualityControlChecklistItem` | `quality_control_checklist_items` | auditItems→QualityControlAuditItem (HasMany) |
| `QualityControlItemJournal` | `quality_control_item_journals` | branch→Branch (BelongsTo), creator→User (BelongsTo), details→QualityControlItemJournalDetail (HasMany), attachments→QualityControlItemJournalAttachment (HasMany) |
| `QualityControlItemJournalAttachment` | `quality_control_item_journal_attachments` | journal→QualityControlItemJournal (BelongsTo) |
| `QualityControlItemJournalDetail` | `quality_control_item_journal_details` | journal→QualityControlItemJournal (BelongsTo) |

## Inventory

| Model | Tabel | Relasi |
|---|---|---|
| `GoodsReceipt` | `goods_receipts` | submittedBy→User (BelongsTo), localBranch→Branch (BelongsTo), items→GoodsReceiptItem (HasMany), vendorComplianceIncidents→VendorComplianceIncident (HasMany) |
| `GoodsReceiptExpiry` | `goods_receipt_expiries` | item→GoodsReceiptItem (BelongsTo) |
| `GoodsReceiptItem` | `goods_receipt_items` | goodsReceipt→GoodsReceipt (BelongsTo), expiries→GoodsReceiptExpiry (HasMany), vendorComplianceIncident→VendorComplianceIncident (HasOne) |
| `Location` | `locations` | branch→Branch (BelongsTo), parent→self (BelongsTo), children→self (HasMany) |
| `LocationType` | `location_types` | — |
| `ProductSetting` | `product_settings` | locations→Location (BelongsToMany) |
| `StockCard` | `stock_cards` | branch→Branch (BelongsTo), submittedBy→User (BelongsTo), supervisorReviewer→User (BelongsTo), financeReviewer→User (BelongsTo), entries→StockCardEntry (HasMany), employees→StockCardEmployee (HasMany), approvals→StockCardApproval (HasMany) |
| `StockCardApproval` | `stock_card_approvals` | stockCard→StockCard (BelongsTo), actor→User (BelongsTo) |
| `StockCardEmployee` | `stock_card_employees` | stockCard→StockCard (BelongsTo), employee→Employee (BelongsTo) |
| `StockCardEntry` | `stock_card_entries` | stockCard→StockCard (BelongsTo) |
| `StockCardSetting` | `stock_card_settings` | — |

## Finance

| Model | Tabel | Relasi |
|---|---|---|
| `BasketSizeEmployeeRecord` | `basket_size_employee_records` | basketSizeRecord→BasketSizeRecord (BelongsTo), salesReport→SalesReport (BelongsTo), employee→Employee (BelongsTo) |
| `BasketSizeRecord` | `basket_size_records` | salesReport→SalesReport (BelongsTo), branch→Branch (BelongsTo), shift→BranchSalesShift (BelongsTo), employeeRecords→BasketSizeEmployeeRecord (HasMany) |
| `ComplimentType` | `compliment_types` | salesReportCompliments→SalesReportCompliment (HasMany) |
| `SalesReport` | `sales_reports` | branch→Branch (BelongsTo), employees→SalesReportEmployee (HasMany), entries→SalesReportEntry (HasMany), shiftSubmissions→SalesReportShiftSubmission (HasMany), reconciliations→SalesReportReconciliation (HasMany), approvals→SalesReportApproval (HasMany), esbTransactions→SalesReportEsbTransaction (HasMany), basketSizeRecords→BasketSizeRecord (HasMany), compliments→SalesReportCompliment (HasMany), voids→SalesReportVoid (HasMany), supervisorReviewer→User (BelongsTo), financeReviewer→User (BelongsTo) |
| `SalesReportApproval` | `sales_report_approvals` | salesReport→SalesReport (BelongsTo), actor→User (BelongsTo) |
| `SalesReportCompliment` | `sales_report_compliments` | salesReport→SalesReport (BelongsTo), complimentType→ComplimentType (BelongsTo), submittedBy→User (BelongsTo) |
| `SalesReportEmployee` | `sales_report_employees` | salesReport→SalesReport (BelongsTo), employee→Employee (BelongsTo) |
| `SalesReportEntry` | `sales_report_entries` | salesReport→SalesReport (BelongsTo) |
| `SalesReportEsbTransaction` | `sales_report_esb_transactions` | salesReport→SalesReport (BelongsTo) |
| `SalesReportReconciliation` | `sales_report_reconciliations` | salesReport→SalesReport (BelongsTo) |
| `SalesReportSettings` | `sales_report_settings` | — |
| `SalesReportShiftSubmission` | `sales_report_shift_submissions` | salesReport→SalesReport (BelongsTo), submittedBy→User (BelongsTo) |
| `SalesReportVoid` | `sales_report_voids` | salesReport→SalesReport (BelongsTo), submittedBy→User (BelongsTo) |

## Brand Marketing

| Model | Tabel | Relasi |
|---|---|---|
| `ContentRequest` | `content_requests` | statusHistories→ContentRequestStatusHistory (HasMany), requester→User (BelongsTo), branch→Branch (BelongsTo) |
| `ContentRequestStatusHistory` | `content_request_status_histories` | contentRequest→ContentRequest (BelongsTo), changedBy→User (BelongsTo) |
| `DesignCategory` | `design_categories` | designRequests→DesignRequest (HasMany) |
| `DesignRequest` | `design_requests` | statusHistories→DesignRequestStatusHistory (HasMany), requester→User (BelongsTo), branch→Branch (BelongsTo), category→DesignCategory (BelongsTo) |
| `DesignRequestStatusHistory` | `design_request_status_histories` | designRequest→DesignRequest (BelongsTo), changedBy→User (BelongsTo) |

## Analytics

| Model | Tabel | Relasi |
|---|---|---|
| `OrderingDailyMetric` | `ordering_daily_metrics` | branch→Branch (BelongsTo) |
| `OrderingForecastItem` | `ordering_forecast_items` | run→OrderingForecastRun (BelongsTo), branch→Branch (BelongsTo) |
| `OrderingForecastRun` | `ordering_forecast_runs` | branch→Branch (BelongsTo), items→OrderingForecastItem (HasMany) |
| `OrderingSyncRun` | `ordering_sync_runs` | branch→Branch (BelongsTo) |
