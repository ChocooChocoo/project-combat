<?php

return [
    'trends' => ['months' => 12],
    'forecast' => ['history_months' => 12, 'ahead_months' => 3, 'minimum_trend_months' => 3],
    'falling_attendance' => ['window_sessions' => 4, 'drop_points' => 20, 'current_below' => 60],
    'low_use_slot' => ['minimum_sessions' => 3, 'fill_below' => 25],
    'high_no_show' => ['minimum_expected' => 5, 'rate_at_least' => 30],
];
