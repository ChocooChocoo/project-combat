import assert from 'node:assert/strict'
import test from 'node:test'
import { VIEWS, decisionUrl, validRange, rateLabel, moneyValue, readOptions, readPayload, forecastMethodLabel, chartCoordinates, fitLabel } from '../src/lib/decision-support.ts'

test('all twelve keys and encoded query parameters are explicit', () => {
  assert.deepEqual(VIEWS.map(({ key }) => key), ['attendance', 'memberships', 'athletes', 'services', 'customers', 'employees', 'money', 'stock', 'trends', 'forecast', 'problems', 'sustainability'])
  assert.equal(decisionUrl(7, 'forecast', '2026-11-01', '2026-11-07', 'cash_flow'), '/gyms/7/decision-support/forecast?from=2026-11-01&to=2026-11-07&basis=cash_flow')
  assert.throws(() => decisionUrl(7, 'unknown', '2026-11-01', '2026-11-07'))
  assert.throws(() => decisionUrl(7, 'attendance', 'bad&x=1', '2026-11-07'))
})

test('date ranges require real ISO dates and inclusive ordered ends', () => {
  assert.equal(validRange('2024-02-29', '2024-02-29'), true)
  for (const [from, to] of [['2026-02-29', '2026-03-01'], ['2026-1-01', '2026-11-07'], ['', '2026-11-07'], ['2026-11-08', '2026-11-07']]) assert.equal(validRange(from, to), false)
  assert.equal(validRange('2000-01-01', '2026-11-07'), true)
})

test('formatting preserves undefined, zero and signed server values', () => {
  assert.equal(rateLabel(null), 'Not available')
  assert.equal(rateLabel(0), '0%')
  assert.equal(rateLabel(66.67), '66.67%')
  assert.equal(rateLabel(-25), '-25%')
  assert.equal(moneyValue('-123.45'), '-123.45')
  assert.throws(() => moneyValue(123))
  assert.throws(() => moneyValue('123'))
})

test('strict options reader rejects malformed defaults and missing thresholds', () => {
  assert.throws(() => readOptions({ today: '2026-11-07' }))
  assert.throws(() => readOptions({ today: 'bad', default_range: { from: 'bad', to: 'bad' } }))
})

test('readers never invent summary or totals from buckets', () => {
  assert.throws(() => readPayload('attendance', { buckets: [] }))
  assert.throws(() => readPayload('money', { income_expense: { buckets: [{ income: '10.00' }] } }))
  assert.throws(() => readPayload('forecast', { method: 'least_squares', buckets: [] }))
})

test('forecast labels preserve server names and reject unknown methods', () => {
  assert.equal(forecastMethodLabel({ method: 'average', method_name: 'average, not enough history' }), 'average, not enough history')
  assert.equal(forecastMethodLabel({ method: 'least_squares', method_name: 'Straight-line least-squares trend' }), 'Straight-line least-squares trend')
  assert.equal(forecastMethodLabel({ method: 'no_history', method_name: 'No history' }), 'No history')
  assert.throws(() => forecastMethodLabel({ method: 'magic', method_name: 'Prediction' }))
})

const meta = { from: '2026-11-01', to: '2026-11-07', timezone: 'Asia/Manila', generated_at: '2026-11-07T08:00:00+08:00', has_data: false, notes: ['Server denominator note'] }
const attendanceCounts = { present: 0, late: 0, absent: 0, no_show: 0, attended: 0, marked: 0, awaiting_attendance: 0, unknown_markers: 0, cancelled_session_markers: 0, attendance_rate: null, no_show_rate: null }
const useCounts = { sessions: 0, delivered_sessions: 0, scheduled_minutes: 0, delivered_minutes: 0, held_seats: 0, confirmed_bookings: 0, capacity: 0, invalid_capacity_sessions: 0, fill_rate: null }

test('problem reader preserves server thresholds and all evidence without creating flags', () => {
  const thresholds = { trends:{months:12},forecast:{history_months:12,ahead_months:3,minimum_trend_months:3},falling_attendance:{window_sessions:4,drop_points:20,current_below:60},low_use_slot:{minimum_sessions:3,fill_below:25},high_no_show:{minimum_expected:5,rate_at_least:30} }
  const payload = {meta,thresholds,summary:{falling_attendance:1,low_use_slot:1,high_no_show:1,total:3},items:[
    {code:'falling_attendance',key:'class:1',name:'Boxing',evidence:{previous:{from:'2026-11-01',to:'2026-11-04',attended:32,marked:40,rate:80},current:{from:'2026-11-05',to:'2026-11-08',attended:20,marked:40,rate:50},drop_points:30,session_ids:[1,2,3,4,5,6,7,8]}},
    {code:'low_use_slot',key:'0:10',name:'Sunday 10:00',evidence:{weekday:0,hour:10,sessions:3,held_seats:3,capacity:110,average_fill:20,session_ids:[1,2,3]}},
    {code:'high_no_show',key:'class:1',name:'Boxing',evidence:{from:'2026-11-01',to:'2026-11-30',marked:10,no_show:3,awaiting_attendance:1,rate:30}},
  ]}
  assert.equal(readPayload('problems',payload),payload)
  assert.throws(()=>readPayload('problems',{...payload,thresholds:{}}))
  assert.throws(()=>readPayload('problems',{...payload,summary:undefined}))
  assert.throws(()=>readPayload('problems',{...payload,items:[{...payload.items[0],evidence:{}}]}))
  assert.throws(()=>readPayload('problems',{...payload,items:[{...payload.items[0],code:'invented'}]}))
  assert.equal(readPayload('problems',{...payload,items:[],summary:{falling_attendance:0,low_use_slot:0,high_no_show:0,total:0}}).items.length,0)
})

test('training readers preserve server totals and reject missing or mistyped counters', () => {
  const attendance = { meta, summary: { ...attendanceCounts, attended: 123, attendance_rate: 66.67 }, classes: [], weekdays: Array.from({length:7}, (_,weekday)=>({weekday,label:String(weekday),class_attended:0,gym_visits:0,total:0})), hours: Array.from({length:24},(_,hour)=>({hour,label:String(hour),class_attended:0,gym_visits:0,total:0})), peaks: { weekdays: [], hours: [] } }
  const athletes = { meta, attribution: 'coach_based_on_attendance', summary: { promotion: 0, assessment: 0, competition: 0, achievement: 0, attending_athletes: 0, athletes_with_progress: 0, effectiveness_rate: null }, buckets: [], disciplines: [], coaches: [], results: [] }
  const services = { meta, summary: useCounts, services: [], most_booked: [], least_booked: [], classes: [], coaches: [], rooms: [], private_coaching: { offered_sessions: 0, booked_sessions: 0, booking_rate: null } }
  for (const [view,payload] of [['attendance',attendance],['athletes',athletes],['services',services]]) {
    assert.equal(readPayload(view,payload),payload)
    assert.equal(readPayload(view,payload).meta.notes[0],'Server denominator note')
    assert.throws(()=>readPayload(view,{...payload,summary:{}}))
  }
  assert.equal(readPayload('attendance',attendance).summary.attended,123)
  assert.throws(()=>readPayload('attendance',{...attendance,summary:{...attendance.summary,attended:'123'}}))
  assert.throws(()=>readPayload('attendance',{...attendance,weekdays:attendance.weekdays.map(row=>({...row,weekday:0}))}))
  assert.throws(()=>readPayload('athletes',{...athletes,attribution:'recorded_by'}))
  assert.throws(()=>readPayload('services',{...services,private_coaching:{}}))
})

test('signed chart geometry crosses zero and preserves absent points without deriving totals', () => {
  const { baseline,points }=chartCoordinates([-10, null, 10])
  assert.equal(baseline,50)
  assert.deepEqual(points,[{x:0,y:100},null,{x:100,y:0}])
  assert.equal(chartCoordinates([0]).points[0].x,50)
})

test('membership and customer readers preserve cohort counts, null rates and server notes', () => {
  const memberships = { meta, summary: { new: 1, renewed: 3, expired: 2, cancelled: 3, active_at_start: 4, churned_from_start: 2, retained_from_start: 2, active_at_end: 3, retention_rate: 50, renewal_rate: 60, missing_activation_history: 1 }, buckets: [{period:'2026-11',new:1,renewed:3,expired:2,cancelled:3}] }
  const customers = { meta, summary: { leads: 4, converted_leads: 2, conversion_rate: 50, members_gained: 3, feedback: 0, concerns_raised: 1, concerns_open_at_end: 2, concerns_resolved: 1 }, sources: [{source:'walk_in',leads:4,converted_leads:2,conversion_rate:50}] }
  for(const [view,payload] of [['memberships',memberships],['customers',customers]]) {
    assert.equal(readPayload(view,payload),payload)
    assert.throws(()=>readPayload(view,{...payload,summary:{}}))
  }
  assert.throws(()=>readPayload('memberships',{...memberships,buckets:[{period:'2026-11'}]}))
  assert.throws(()=>readPayload('customers',{...customers,sources:[{source:'walk_in',leads:'4'}]}))
  assert.equal(readPayload('customers',{...customers,summary:{...customers.summary,conversion_rate:null}}).summary.conversion_rate,null)
})

test('business readers require backend money, current snapshot bases and server payroll mask', () => {
  const staff = { present: 0, late: 0, absent: 0, attendance_rate: null, late_rate: null, worked_minutes: 0, late_minutes: 0, undertime_minutes: 0, missing_clock_out: 0 }
  const employees = { meta, pay_visible: false, summary: staff, attendance: [], workload: [], payroll: { buckets: [], totals: { gross: '15000.00', net: '13500.00', employer_statutory: '1290.00', count: 2 } } }
  const money = { meta, income_expense: { buckets: [], totals: {income:'1600.00',expenses:'0.00',net:'1600.00'} }, cash_flow: {buckets:[],totals:{in:'1600.00',out:'0.00',net:'1600.00'}}, income_types:[{type:'membership',amount:'1000.00',share:125},{type:'merchandise',amount:'-200.00',share:-25}],session_services:[{service_id:null,name:'Unassigned',amount:'400.00'}],service_allocation_anomalies:1,receivables:{basis:'current_snapshot',as_of:'2026-11-07',historical_available:false,outstanding:'900.00',debtor_count:1,members:[{user_id:7,member_name:'Fixture member',total:'900.00'}]} }
  const stock = {meta,as_of:'2026-11-07',summary:{items:1,low_items:1,out_items:0},items:[{id:1,name:'Gloves',category:'equipment',unit:'pair',location:null,quantity:5,min_level:5,low:true}],purchasing:{basis:'created_in_range_current_status',requests:[],orders:[],receipts:{count:0,total:'0.00'}}}
  for(const [view,payload] of [['employees',employees],['money',money],['stock',stock]]) assert.equal(readPayload(view,payload),payload)
  assert.throws(()=>readPayload('employees',{...employees,payroll_by_employee:[]}))
  assert.throws(()=>readPayload('employees',{...employees,pay_visible:true}))
  assert.equal(readPayload('employees',{...employees,pay_visible:true,payroll_by_employee:[]}).pay_visible,true)
  assert.throws(()=>readPayload('money',{...money,income_expense:{buckets:[]}}))
  assert.throws(()=>readPayload('money',{...money,receivables:{...money.receivables,basis:'historical'}}))
  assert.throws(()=>readPayload('stock',{...stock,purchasing:{...stock.purchasing,receipts:{count:0,total:0}}}))
  assert.equal(readPayload('money',money).income_types[1].share,-25)
  assert.equal(readPayload('employees',employees).payroll.totals.gross,'15000.00')
})

test('trend and forecast readers preserve server windows, projections and numeric fit without percent formatting', () => {
  const trends = {meta,window:{from:'2025-12-01',to:'2026-11-07',months:12,partial_final_month:true},first_records:{attendance:null,memberships:'2025-01-01',revenue:null},buckets:Array.from({length:12},(_,index)=>({period:index===0?'2025-12':`2026-${String(index).padStart(2,'0')}`,attended:0,marked:0,attendance_rate:null,new:0,renewed:0,expired:0,cancelled:0,active_at_end:1,revenue:'0.00'})),totals:{attended:0,marked:0,attendance_rate:null,new:0,renewed:0,expired:0,cancelled:0,revenue:'0.00'},closing_active:1}
  const forecast = {meta,basis:'income_expense',series_labels:{income:'Income',expenses:'Expenses',net:'Income less expenses'},method:'average',method_name:'average, not enough history',window:{from:'2026-10-01',to:'2026-10-31',months:1,excluded_partial_month:'2026-11'},fit:{income_r_squared:null,expense_r_squared:null},history:[{period:'2026-10',income:'100.00',expenses:'200.00',net:'-100.00'}],buckets:[{period:'2026-11',income:'100.00',expenses:'200.00',net:'-100.00'}],totals:{income:'300.00',expenses:'600.00',net:'-300.00'}}
  assert.equal(readPayload('trends',trends),trends)
  assert.equal(readPayload('forecast',forecast),forecast)
  assert.equal(readPayload('forecast',forecast).totals.net,'-300.00')
  assert.throws(()=>readPayload('trends',{...trends,closing_active:undefined}))
  assert.throws(()=>readPayload('forecast',{...forecast,fit:{income_r_squared:90,expense_r_squared:1}}))
  assert.throws(()=>readPayload('forecast',{...forecast,basis:'accrual_renamed_as_cash'}))
  assert.throws(()=>readPayload('forecast',{...forecast,totals:undefined}))
  assert.equal(fitLabel(1),'1')
  assert.equal(fitLabel(0.67),'0.67')
  assert.equal(fitLabel(null),'Not available')
  assert.throws(()=>fitLabel(90))
  assert.equal(readPayload('forecast',{...forecast,method:'no_history',method_name:'No history',window:{from:null,to:'2026-10-31',months:0,excluded_partial_month:'2026-11'},history:[],buckets:[],totals:{income:'0.00',expenses:'0.00',net:'0.00'}}).method,'no_history')
})

test('sustainability reader requires all five server measures, numeric budget rates and cash basis', () => {
  const amounts={income:'0.00',expenses:'0.00',net:'0.00'}
  const payload={meta,financial:{buckets:[],totals:amounts},revenue_mix:{income_types:[],session_services:[],service_allocation_anomalies:0},membership:{new:0,renewed:0,expired:0,cancelled:0,active_at_start:0,churned_from_start:0,retained_from_start:0,active_at_end:0,retention_rate:null,renewal_rate:null,missing_activation_history:0},utilization:{classes:[{class_key:'test',service_id:null,name:'Test class',...useCounts}],rooms:[]},budget:{month:'2026-11',basis:'whole_month_containing_range_end',rows:[{kind:'income',category:'membership',budget:'1000.00',actual:'650.00',difference:'350.00',percentage:65}],totals:[{kind:'income',budget:'1000.00',actual:'650.00',difference:'350.00',percentage:65}]},cash_flow_forecast:{meta,basis:'cash_flow',series_labels:{income:'Cash in',expenses:'Cash out',net:'Cash-flow net'},method:'no_history',method_name:'No history',window:{from:null,to:'2026-10-31',months:0,excluded_partial_month:'2026-11'},fit:{income_r_squared:null,expense_r_squared:null},history:[],buckets:[],totals:amounts}}
  assert.equal(readPayload('sustainability',payload),payload)
  for(const key of ['financial','revenue_mix','membership','utilization','budget','cash_flow_forecast']) assert.throws(()=>readPayload('sustainability',{...payload,[key]:undefined}))
  assert.throws(()=>readPayload('sustainability',{...payload,budget:{...payload.budget,rows:[{...payload.budget.rows[0],percentage:'65.00'}]}}))
  assert.throws(()=>readPayload('sustainability',{...payload,cash_flow_forecast:{...payload.cash_flow_forecast,basis:'income_expense'}}))
  assert.throws(()=>readPayload('sustainability',{...payload,financial:{buckets:[]}}))
})
