<?php
session_start();
require_once 'auth.php';
require_once 'layout.php';
require_once 'schedule_view.php';

$auth = require_auth(['admin']);
render_header($auth, 'Schedule overview', 'admin_schedule.php', 'All customer bookings in one calendar. Click a booking to approve, decline, assign a technician or adjust the schedule.');
render_schedule_view($conn, 'admin_schedule.php');
render_footer();
