<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';
require_once 'schedule_view.php';

$auth = require_auth(['dispatcher']);
render_header($auth, 'Dispatch schedule', 'dispatcher_dashboard.php', 'View all bookings, assign technicians, approve or decline requests and adjust schedules.');
render_schedule_view($conn, 'dispatcher_dashboard.php');
render_footer();
