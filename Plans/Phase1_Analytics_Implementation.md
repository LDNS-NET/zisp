# Phase 1: Advanced Analytics & Business Intelligence - Implementation Plan

## Overview
Build a comprehensive analytics suite that provides real-time network intelligence, predictive insights, and advanced financial reporting to transform the dashboard into a true command center.

---

## Feature Breakdown

### 1. Live Network Topology Map 🗺️

#### Backend Components
**New Controller:** `app/Http/Controllers/Tenants/NetworkTopologyController.php`
- `getTopology()` - Fetch all routers with relationships and status
- `getDeviceDetails($id)` - Get detailed stats for a specific device
- `discoverDevices()` - Auto-discover connected devices via SNMP

**API Endpoints:**
```php
Route::get('/api/network/topology', [NetworkTopologyController::class, 'getTopology']);
Route::get('/api/network/device/{id}', [NetworkTopologyController::class, 'getDeviceDetails']);
Route::post('/api/network/discover', [NetworkTopologyController::class, 'discoverDevices']);
```

**Data Structure:**
```json
{
  "nodes": [
    {
      "id": 1,
      "name": "Router-Main",
      "type": "router",
      "status": "online",
      "ip": "192.168.1.1",
      "cpu": 45,
      "memory": 60,
      "uptime": "15d 3h",
      "connections": [2, 3]
    }
  ],
  "edges": [
    {"from": 1, "to": 2, "bandwidth": "1Gbps"}
  ]
}
```

#### Frontend Components
**New Page:** `resources/js/Pages/Analytics/NetworkTopology.vue`
- Interactive canvas using **vis-network** or **D3.js**
- Real-time status updates via polling/WebSockets
- Click-to-zoom and pan controls
- Device info sidebar on click
- Color-coded status (green/yellow/red)

**UI Features:**
- Auto-layout algorithm for optimal visualization
- Search and filter devices
- Export topology as PNG/SVG
- Legend for device types and statuses

---

### 2. Advanced Traffic Analytics 📈

#### Backend Components
**New Controller:** `app/Http/Controllers/Tenants/TrafficAnalyticsController.php`

**Methods:**
- `getUserBandwidth($userId, $period)` - Get user consumption over time
- `getTopConsumers($limit, $period)` - Top N bandwidth users
- `getProtocolBreakdown()` - Traffic by protocol (HTTP, HTTPS, etc.)
- `detectAnomalies()` - ML-based unusual pattern detection

**Database Schema Enhancement:**
```sql
-- New table for aggregated traffic stats
CREATE TABLE tenant_traffic_analytics (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,
    user_id BIGINT,
    date DATE,
    hour TINYINT,
    bytes_in BIGINT,
    bytes_out BIGINT,
    protocol VARCHAR(50),
    created_at TIMESTAMP
);

-- Index for fast queries
CREATE INDEX idx_traffic_user_date ON tenant_traffic_analytics(user_id, date);
CREATE INDEX idx_traffic_tenant_date ON tenant_traffic_analytics(tenant_id, date);
```

**Aggregation Job:**
```php
// app/Console/Commands/AggregateTrafficData.php
// Runs hourly to aggregate Radacct data
```

#### Machine Learning Component
**Anomaly Detection:**
- Use **Laravel ML** or **Python microservice**
- Train on historical usage patterns
- Flag deviations > 2 standard deviations
- Alert on potential abuse or compromised accounts

#### Frontend Components
**New Page:** `resources/js/Pages/Analytics/TrafficAnalytics.vue`

**Widgets:**
1. **User Bandwidth Graph**
   - Line chart showing hourly/daily/monthly usage
   - Stacked area for upload/download
   - Date range picker

2. **Top Consumers Table**
   - Sortable table with user, usage, percentage
   - Click to view user details
   - Export to CSV

3. **Protocol Breakdown**
   - Pie chart or donut chart
   - Hover for percentages
   - Filter by time period

4. **Anomaly Alerts**
   - List of flagged users with severity
   - Quick actions (suspend, investigate)

**Libraries:**
- **Chart.js** or **ApexCharts** for visualizations
- **date-fns** for date manipulation

---

### 3. Predictive Analytics 🔮

#### Backend Components
**New Service:** `app/Services/PredictiveAnalyticsService.php`

**Methods:**
- `predictChurn()` - Identify at-risk customers
- `forecastRevenue($months)` - Revenue projection
- `recommendCapacity()` - Network expansion suggestions
- `detectSeasonality()` - Usage pattern analysis

**Churn Prediction Model:**
```php
// Factors considered:
- Payment history (late payments, failures)
- Usage trends (declining usage)
- Support tickets (frequency, sentiment)
- Contract end date proximity
- Competitor activity (if available)

// Output: Risk score 0-100 per user
```

**Revenue Forecasting:**
- Time series analysis using **Prophet** or **ARIMA**
- Consider historical data, growth rate, seasonality
- Confidence intervals for projections

#### Database Schema
```sql
CREATE TABLE tenant_predictions (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,
    prediction_type VARCHAR(50), -- 'churn', 'revenue', 'capacity'
    entity_id BIGINT, -- user_id or null for tenant-wide
    prediction_value DECIMAL(10,2),
    confidence DECIMAL(5,2),
    factors JSON,
    predicted_at TIMESTAMP,
    valid_until TIMESTAMP
);
```

#### Frontend Components
**Dashboard Widgets:**

1. **Churn Risk List**
   - Table of high-risk users
   - Risk score with color coding
   - Recommended actions (retention offer)

2. **Revenue Forecast Chart**
   - Line chart with confidence bands
   - Toggle scenarios (optimistic/realistic/pessimistic)

3. **Capacity Planner**
   - Current vs projected capacity
   - Recommended upgrade timeline
   - Cost estimates

---

### 4. Advanced Reporting Suite 📊

#### Backend Components
**New Controller:** `app/Http/Controllers/Tenants/ReportBuilderController.php`

**Methods:**
- `getAvailableMetrics()` - List all reportable data points
- `buildReport($config)` - Generate custom report
- `scheduleReport($config, $schedule)` - Set up automated reports
- `exportReport($id, $format)` - Export as PDF/Excel/CSV

**Report Configuration Schema:**
```json
{
  "name": "Monthly Revenue by Zone",
  "metrics": ["revenue", "user_count"],
  "dimensions": ["location", "package_type"],
  "filters": {
    "date_range": "last_30_days",
    "status": "active"
  },
  "groupBy": "location",
  "sortBy": "revenue",
  "sortOrder": "desc",
  "format": "table",
  "schedule": {
    "frequency": "monthly",
    "day": 1,
    "recipients": ["admin@example.com"]
  }
}
```

**Database Schema:**
```sql
CREATE TABLE tenant_custom_reports (
    id BIGINT PRIMARY KEY,
    tenant_id BIGINT,
    name VARCHAR(255),
    config JSON,
    schedule JSON,
    created_by BIGINT,
    created_at TIMESTAMP
);

CREATE TABLE tenant_report_runs (
    id BIGINT PRIMARY KEY,
    report_id BIGINT,
    generated_at TIMESTAMP,
    file_path VARCHAR(255),
    status VARCHAR(50)
);
```

#### Frontend Components
**New Page:** `resources/js/Pages/Analytics/ReportBuilder.vue`

**Features:**
1. **Drag-and-Drop Builder**
   - Metric selector (revenue, users, bandwidth, etc.)
   - Dimension selector (time, location, package)
   - Filter builder with conditions
   - Preview pane

2. **Report Templates**
   - Pre-built reports (Revenue Summary, User Growth, etc.)
   - Save custom reports as templates

3. **Scheduling Interface**
   - Frequency picker (daily, weekly, monthly)
   - Email recipient management
   - Format selection (PDF, Excel, CSV)

4. **Report Library**
   - List of saved reports
   - Quick run or schedule
   - View history and download past runs

**Export Libraries:**
- **Laravel Excel** for Excel/CSV
- **DomPDF** or **Snappy** for PDF generation

---

### 5. Financial Intelligence Dashboards 💰

#### Backend Enhancements
**Update:** `app/Http/Controllers/DashboardController.php`

**New Methods:**
- `getCashFlowProjection()` - 90-day cash flow forecast
- `getAccountsReceivable()` - Aging analysis
- `getPaymentMetrics()` - Success rates, methods breakdown
- `getRevenueHeatmap()` - Revenue by zone/tower

**Calculations:**
```php
// Cash Flow Projection
- Current MRR (Monthly Recurring Revenue)
- Expected new customers (based on trends)
- Expected churn (based on predictions)
- Seasonal adjustments
- Outstanding invoices collection probability

// Accounts Receivable Aging
- 0-30 days overdue
- 31-60 days overdue
- 61-90 days overdue
- 90+ days overdue
```

#### Frontend Components
**Dashboard Widgets:**

1. **Cash Flow Chart**
   - Line chart with projected vs actual
   - Color-coded zones (positive/negative)
   - Drill-down to see details

2. **AR Aging Table**
   - Grouped by age buckets
   - Click to see user list
   - Quick action buttons (send reminder, suspend)

3. **Payment Success Tracker**
   - Gauge chart showing success rate
   - Breakdown by payment method
   - Trend over time

4. **Revenue Heatmap**
   - Geographic map or grid
   - Color intensity = revenue
   - Hover for exact figures
   - Click to filter dashboard by zone

---

## Implementation Phases

### Phase 1.1: Foundation (Week 1-2)
- [x] Set up database tables for traffic analytics
- [x] Create aggregation job for Radacct data
- [x] Build NetworkTopologyController with basic API
- [x] Create TrafficAnalyticsController
- [x] Set up Chart.js/ApexCharts in frontend

### Phase 1.2: Network Topology (Week 3-4)
- [x] Implement topology data fetching
- [x] Build interactive topology visualization
- [x] Add device status indicators
- [x] Implement click-to-manage features
- [ ] Add auto-discovery (optional)

### Phase 1.3: Traffic Analytics (Week 5-6)
- [x] Build user bandwidth graphs
- [x] Create top consumers dashboard
- [x] Implement protocol breakdown
- [x] Add anomaly detection (basic rules first)
- [x] Create alert system for anomalies

### Phase 1.4: Predictive Analytics (Week 7-8)
- [x] Implement churn prediction model
- [x] Build revenue forecasting
- [x] Create capacity planning tool (heuristic)
- [x] Add seasonality detection
- [x] Build prediction dashboard widgets

### Phase 1.5: Report Builder (Week 9-10)
- [ ] Create report configuration system
- [ ] Build drag-and-drop report builder UI
- [ ] Implement report generation engine
- [ ] Add scheduling system
- [ ] Create export functionality (PDF, Excel, CSV)

### Phase 1.6: Financial Dashboards (Week 11-12)
- [ ] Implement cash flow projections
- [ ] Build AR aging analysis
- [ ] Create payment metrics tracker
- [ ] Build revenue heatmap
- [ ] Integrate all widgets into main dashboard

---

## Technical Stack

### Backend
- **Laravel 10+** - Core framework
- **Laravel Excel** - Excel/CSV exports
- **DomPDF/Snappy** - PDF generation
- **Laravel Horizon** - Job queue monitoring
- **Redis** - Caching and real-time data

### Frontend
- **Vue 3** - UI framework
- **Inertia.js** - SPA without API
- **Chart.js/ApexCharts** - Data visualization
- **vis-network** or **D3.js** - Network topology
- **TailwindCSS** - Styling
- **Headless UI** - Accessible components

### Machine Learning (Optional)
- **Python microservice** with Flask/FastAPI
- **scikit-learn** - ML algorithms
- **Prophet** - Time series forecasting
- **Docker** - Containerization

---

## Database Migrations

```bash
php artisan make:migration create_tenant_traffic_analytics_table
php artisan make:migration create_tenant_predictions_table
php artisan make:migration create_tenant_custom_reports_table
php artisan make:migration create_tenant_report_runs_table
```

---

## Testing Strategy

### Unit Tests
- Test all analytics calculations
- Test report generation logic
- Test prediction algorithms

### Integration Tests
- Test API endpoints
- Test job scheduling
- Test export functionality

### Performance Tests
- Load test with 10k+ users
- Optimize queries with indexes
- Cache frequently accessed data

---

## Success Criteria

- [ ] Network topology loads in <3 seconds
- [ ] Traffic analytics queries complete in <1 second
- [ ] Reports generate in <30 seconds for 10k users
- [ ] Predictions update daily automatically
- [ ] All visualizations are responsive and interactive
- [ ] Export functionality works for all formats
- [ ] Dashboard remains performant with real-time updates

---

## Next Steps

1. **Review this plan** - Confirm scope and timeline
2. **Set up development environment** - Ensure all tools installed
3. **Create feature branch** - `git checkout -b feature/phase1-analytics`
4. **Start with Phase 1.1** - Foundation work
5. **Iterate and test** - Build incrementally with testing

**Ready to begin?** Let me know if you'd like to adjust anything or if we should start implementing Phase 1.1!
