<?php
/**
 * Staff dashboard - "at a glance" home screen.
 *
 * VISUAL ONLY FOR NOW: the numbers and activity rows below are hardcoded
 * sample data. They get replaced with real queries once circulation,
 * holds, and fines exist.
 */
$layout = 'staff';
Auth::requireRole(['admin', 'librarian']);

$recentActivity = [
    ['time' => '10:42 AM', 'action' => 'Checked out - Clean Code',                'who' => 'M. Reyes (staff)',  'badge' => null],
    ['time' => '10:15 AM', 'action' => 'Returned - The Pragmatic Programmer',      'who' => 'A. Cruz (student)', 'badge' => null],
    ['time' => '9:50 AM',  'action' => 'Fine paid - PHP 25.00',                    'who' => 'J. Santos (student)','badge' => null],
    ['time' => '9:30 AM',  'action' => 'Hold placed - Introduction to Algorithms', 'who' => 'K. Lim (faculty)',  'badge' => 'On hold'],
    ['time' => '9:05 AM',  'action' => 'Overdue reminder sent',                    'who' => 'System',            'badge' => 'Overdue'],
];
?>
<h1>Dashboard</h1>
<p class="muted">Today at a glance.</p>

<div class="stat-row">
    <div>
        <div class="stat-number">42</div>
        <div class="stat-label">Checked out today</div>
    </div>
    <div>
        <div class="stat-number">7</div>
        <div class="stat-label">Overdue</div>
    </div>
    <div>
        <div class="stat-number">3</div>
        <div class="stat-label">Holds ready for pickup</div>
    </div>
</div>

<div class="section-heading">
    <h2>Recent activity</h2>
</div>

<table>
    <thead>
        <tr><th>Time</th><th>Action</th><th>Who</th><th></th></tr>
    </thead>
    <tbody>
        <?php foreach ($recentActivity as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['time']) ?></td>
                <td><?= htmlspecialchars($row['action']) ?></td>
                <td><?= htmlspecialchars($row['who']) ?></td>
                <td>
                    <?php if ($row['badge'] === 'Overdue'): ?>
                        <span class="badge badge-overdue">Overdue</span>
                    <?php elseif ($row['badge'] === 'On hold'): ?>
                        <span class="badge badge-hold">On hold</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
