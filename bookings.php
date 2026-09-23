<div class="main">
<?php
$user_level = $_SESSION['user_level'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
$user_group_name = '';
$stmt_current_user = $conn->prepare("SELECT group_name FROM users WHERE user_id = ?");
$stmt_current_user->bind_param("i", $user_id);
$stmt_current_user->execute();
$current_user = $stmt_current_user->get_result()->fetch_assoc();
$user_group_name = $current_user['group_name'] ?? '';
$status_update_error = $_SESSION['status_update_error'] ?? '';
unset($_SESSION['status_update_error']);

$stmt_bookings = $conn->prepare("
    SELECT bookings.*, sections.group_type, booking_statuses.status_name
           , users.first_name, users.last_name, users.email
    FROM bookings
    LEFT JOIN sections ON bookings.group_name = sections.group_name
    LEFT JOIN booking_statuses ON bookings.booking_status_id = booking_statuses.status_id
    LEFT JOIN users ON bookings.user_id = users.user_id
");
$stmt_bookings->execute();
$bookings_result = $stmt_bookings->get_result();

$stmt_counts = $conn->prepare("
    SELECT
        COUNT(DISTINCT item_code) AS unique_items,
        COALESCE(SUM(quantity_needed), 0) AS total_items
    FROM booking_items
    WHERE booking_id = ?
");

$stmt_return_items = $conn->prepare("SELECT booking_id, booking_items.item_code, items.item_name, booking_items.quantity_needed FROM booking_items LEFT JOIN items ON booking_items.item_code = items.item_code ORDER BY booking_items.booking_id, items.item_name");
$stmt_return_items->execute();
$return_items_result = $stmt_return_items->get_result();
$return_items_by_booking = [];
while ($return_item = $return_items_result->fetch_assoc()) {
    $return_items_by_booking[$return_item['booking_id']][] = $return_item;
}
$stmt_assigned_components = $conn->prepare("SELECT bic.booking_id, bic.item_code, bic.component_code FROM booking_item_components bic ORDER BY bic.booking_id, bic.item_code, bic.component_code");
$stmt_assigned_components->execute();
$assigned_components_result = $stmt_assigned_components->get_result();
$assigned_components_by_booking = [];
while ($assigned_component = $assigned_components_result->fetch_assoc()) {
    $assigned_components_by_booking[$assigned_component['booking_id']][$assigned_component['item_code']][] = $assigned_component['component_code'];
}
?>

<?php
$today = date_create(date('Y-m-d'));
$section1 = [];
$section2 = [];
$section3 = [];
$section4 = [];
$section5 = [];
$section6 = [];
$section7 = [];

function render_booking_card($row, $unique_items, $total_items, $user_level, $user_group_name) {
    global $assigned_components_by_booking;
    $booking_id = $row['booking_id'];

    if ($row['group_name'] == $row['group_type']) {
        $displayValue = $row['group_name'];
    } else {
        $displayValue = $row['group_name'] . ' ' . $row['group_type'];
    }

    $start_date = date_format(date_create($row['event_start']), "d/m/y");
    $end_date = date_format(date_create($row['event_end']), "d/m/y");
    $collection_date = $row['collection_date']
        ? date_format(date_create($row['collection_date']), "d/m/y")
        : '';
    $return_date = $row['return_date']
        ? date_format(date_create($row['return_date']), "d/m/y")
        : '';
    $status_name = htmlspecialchars($row['status_name'] ?? 'Unknown');
    $status_id = (int) $row['booking_status_id'];
    $status_class = 'booking-status-' . $status_id;
    $booker_name = htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'Unknown');
    $booker_email = htmlspecialchars($row['email'] ?? '');
    $assigned_codes = [];
    foreach ($assigned_components_by_booking[$booking_id] ?? [] as $item_codes) {
        foreach ($item_codes as $component_code) $assigned_codes[] = htmlspecialchars($component_code);
    }
    $assigned_html = $assigned_codes
        ? "<div class='booking-assigned-components'><span class='booking-info-label'>Assigned physical units</span><span class='booking-info-value'>" . implode(', ', $assigned_codes) . "</span></div>"
        : '';

    $status_button = '';
    if ($user_level === 1 || $user_level === 2 || ($user_level === 3 && $status_id === 2)) {
        $status_button = "
            <button class='status-button' type='button' onclick='openStatusModal({$booking_id}, {$status_id})'>
                Change status
            </button>
        ";
    }

    $can_edit = in_array($user_level, [1, 2], true) || ($user_group_name !== '' && $user_group_name === ($row['group_name'] ?? ''));
    $edit_button = $can_edit
        ? "<button class='edit-booking' type='button' onclick='edit_items({$booking_id})'>Edit</button>"
        : '';

    return "
        <article class='booking-card'>
            <div class='booking-card-header {$status_class}'>
                <h2 class='booking-card-title'>{$row['event_name']}</h2>
                <p class='booking-card-date'>{$start_date} - {$end_date}</p>
                <span class='booking-status-badge'>{$status_name}</span>
            </div>

            <div class='booking-card-content'>
                <div class='booking-info-item'>
                    <span class='booking-info-label'>Equipment Collection Date</span>
                    <span class='booking-info-value'>{$collection_date}</span>
                </div>
                <div class='booking-info-item'>
                    <span class='booking-info-label'>Equipment Return Date</span>
                    <span class='booking-info-value'>{$return_date}</span>
                </div>
                <div class='booking-info-item'>
                    <span class='booking-info-label'>Section / End Use</span>
                    <span class='booking-info-value'>{$displayValue}</span>
                </div>
                <div class='booking-info-item'>
                    <span class='booking-info-label'>Booked By</span>
                    <span class='booking-info-value'>{$booker_name}<br><small>{$booker_email}</small></span>
                </div>
                {$assigned_html}
            </div>

            <div class='booking-card-footer'>
                <div class='booking-stats'>
                    <div class='booking-stat'>
                        <span class='booking-stat-label'>Unique Items</span>
                        <span class='booking-stat-value'>{$unique_items}</span>
                    </div>
                    <div class='booking-stat'>
                        <span class='booking-stat-label'>Total Items</span>
                        <span class='booking-stat-value'>{$total_items}</span>
                    </div>
                </div>
                <div class='booking-actions'>
                    {$edit_button}
                    {$status_button}
                </div>
            </div>
        </article>
    ";
}

while ($row = mysqli_fetch_array($bookings_result, MYSQLI_BOTH)) {
    $booking_id = $row['booking_id'];

    $stmt_counts->bind_param("i", $booking_id);
    $stmt_counts->execute();
    $count_result = $stmt_counts->get_result();
    $count_row = $count_result->fetch_assoc();

    $unique_items = $count_row['unique_items'] ?? 0;
    $total_items = $count_row['total_items'] ?? 0;

    $startDate = date_create($row['event_start']);
    $endDate = date_create($row['event_end']);
    $status_id = (int) $row['booking_status_id'];

    $cardHtml = render_booking_card($row, $unique_items, $total_items, $user_level, $user_group_name);

    if ($status_id === 1 && $endDate >= $today) {
        $section1[] = $cardHtml;
    } elseif ($status_id === 2 && $startDate >= $today) {
        $section2[] = $cardHtml;
    } elseif ($status_id === 2 && $startDate < $today && $endDate >= $today) {
        $section3[] = $cardHtml;
    } elseif ($status_id === 3 && $endDate >= $today) {
        $section3[] = $cardHtml;
    } elseif (in_array($status_id, [2, 3], true) && $endDate < $today) {
        $section4[] = $cardHtml;
    } elseif ($status_id === 4) {
        $section5[] = $cardHtml;
    } elseif ($status_id === 5) {
        $section6[] = $cardHtml;
    } elseif ($status_id === 1 && $endDate < $today) {
        $section7[] = $cardHtml;
    }
}

function render_section($title, $items) {
    $count = count($items);
    $output = "<section class='booking-section'><div class='booking-section-header'><h2>$title</h2><span class='booking-section-count'>$count</span></div>";
    if (count($items) === 0) {
        $output .= "<p>No bookings in this section.</p>";
    } else {
        $output .= "<div class='bookings-grid'>" . implode("", $items) . "</div>";
    }
    $output .= "</section>";
    return $output;
}

?>

<div class="bookings-page-header">
    <h1>Bookings</h1>
</div>

<?php if ($status_update_error !== ''): ?>
    <p class="error"><?php echo htmlspecialchars($status_update_error); ?></p>
<?php endif; ?>

<?php echo render_section('1. Pending Bookings', $section1); ?>
<?php echo render_section('2. Approved Bookings Awaiting Start', $section2); ?>
<?php echo render_section('3. Current Approved or Collected Bookings', $section3); ?>
<?php echo render_section('4. Ended Bookings Awaiting Return', $section4); ?>
<?php echo render_section('5. Returned Bookings', $section5); ?>
<?php echo render_section('6. Cancelled Bookings', $section6); ?>
<?php echo render_section('7. Expired Pending Bookings', $section7); ?>
</div>

<div id="status-modal" class="modal-overlay hidden">
    <div class="modal-content">
        <button type="button" class="close-modal" onclick="closeStatusModal()">×</button>
        <h2>Change Booking Status</h2>
        <form id="status-form" action="booking_status_update.php" method="post">
            <input type="hidden" name="booking_id" id="statusBookingId">
            <div class="booking-info-item">
                <label for="statusSelect" class="booking-info-label">New status</label>
                <select id="statusSelect" name="status_id" class="booking-info-value" required>
                </select>
                <div id="statusNotice" class="booking-info-value status-notice"></div>
            </div>
            <div id="return-items" class="return-items hidden"></div>
            <div class="modal-actions">
                <button type="button" class="cancel-button" onclick="closeStatusModal()">Cancel</button>
                <button type="submit" class="save-button">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="./static/js/faq_funcs.js"></script>
<script>
    const currentUserLevel = <?php echo intval($user_level); ?>;
    const bookingReturnItems = <?php echo json_encode($return_items_by_booking, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const bookingAssignedComponents = <?php echo json_encode($assigned_components_by_booking, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function edit_items($bookingID) {
        window.location.replace(`index.php?page=booking&bookingID=${$bookingID}`);
    }

    function openStatusModal(bookingID, currentStatusID) {
        const statusSelect = document.getElementById('statusSelect');
        const statusNotice = document.getElementById('statusNotice');
        statusSelect.innerHTML = '';
        statusNotice.style.display = 'none';
        statusNotice.textContent = '';

        const options = [];
        if (currentUserLevel === 1 || currentUserLevel === 2) {
            options.push(
                { value: 1, label: 'Pending' },
                { value: 2, label: 'Approved' },
                { value: 3, label: 'Collected' },
                { value: 4, label: 'Returned' },
                { value: 5, label: 'Cancelled' }
            );
        } else if (currentUserLevel === 3 && currentStatusID === 2) {
            options.push(
                { value: 3, label: 'Collected' },
                { value: 4, label: 'Returned' },
                { value: 5, label: 'Cancelled' }
            );
        }

        if (options.length === 0) {
            alert('No status changes are available for your access level and the current booking status.');
            return;
        }

        for (const opt of options) {
            const optionEl = document.createElement('option');
            optionEl.value = opt.value;
            optionEl.textContent = opt.label;
            if (opt.value === currentStatusID) {
                optionEl.selected = true;
            }
            statusSelect.appendChild(optionEl);
        }

        statusSelect.disabled = false;
        document.querySelector('#status-form .save-button').disabled = false;

        statusSelect.onchange = function () {
            const returnItems = document.getElementById('return-items');
            returnItems.innerHTML = '';
            if (Number(statusSelect.value) !== 4) {
                returnItems.classList.add('hidden');
                return;
            }

            returnItems.classList.remove('hidden');
            const items = bookingReturnItems[bookingID] || [];
            returnItems.innerHTML = '<h3>Returned component condition</h3><p>Record the condition of each assigned physical unit.</p>';
            for (const item of items) {
                const heading = document.createElement('p');
                const itemName = document.createElement('strong');
                itemName.textContent = item.item_name || item.item_code;
                heading.append(itemName, ` (${item.quantity_needed} required)`);

                const assigned = bookingAssignedComponents[bookingID]?.[item.item_code] || [];
                returnItems.append(heading);
                for (const componentCode of assigned) {
                    const componentBlock = document.createElement('div');
                    componentBlock.className = 'return-component-block';
                    const componentHeading = document.createElement('strong');
                    componentHeading.textContent = componentCode;
                    componentBlock.append(componentHeading);

                    const qualityLabel = document.createElement('label');
                    qualityLabel.textContent = 'Condition';
                    qualityLabel.htmlFor = `quality-${bookingID}-${componentCode}`;
                    const quality = document.createElement('select');
                    quality.id = qualityLabel.htmlFor;
                    quality.name = `return_items[${item.item_code}][components][${componentCode}][quality_desc]`;
                    quality.className = 'form-input';
                    quality.required = true;
                    for (const qualityOption of ['Good', 'Damaged', 'End of life passed', 'Needs maintenance']) {
                        const option = document.createElement('option');
                        option.value = qualityOption;
                        option.textContent = qualityOption;
                        quality.appendChild(option);
                    }

                    const noteLabel = document.createElement('label');
                    noteLabel.textContent = 'Return note';
                    noteLabel.htmlFor = `note-${bookingID}-${componentCode}`;
                    const note = document.createElement('textarea');
                    note.id = noteLabel.htmlFor;
                    note.name = `return_items[${item.item_code}][components][${componentCode}][return_note]`;
                    note.className = 'form-input';
                    note.rows = 2;
                    note.required = true;
                    componentBlock.append(qualityLabel, quality, noteLabel, note);
                    returnItems.append(componentBlock);
                }
            }
        };

        document.getElementById('statusBookingId').value = bookingID;
        document.getElementById('status-modal').classList.remove('hidden');
    }

    function closeStatusModal() {
        document.getElementById('status-modal').classList.add('hidden');
    }
</script>
</div>