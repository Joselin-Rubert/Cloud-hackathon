<?php
require_once __DIR__ . '/../config/auth.php';
require_api_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed.', [], 405);
verify_csrf();

$uid    = user_id();
$action = $_POST['action'] ?? '';
$id     = (int) ($_POST['id'] ?? 0);

function fetch_expense(int $uid, int $id): array
{
    $stmt = db()->prepare('SELECT * FROM expenses WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $row = $stmt->fetch();
    if (!$row) respond(false, 'Expense not found.', [], 404);
    return $row;
}

function expense_payload(int $uid): array
{
    $stmt = db()->prepare(
        'SELECT id, amount, category, description, expense_date FROM expenses
         WHERE user_id = ? ORDER BY expense_date DESC, id DESC LIMIT 60'
    );
    $stmt->execute([$uid]);
    return [
        'summary'   => expense_summary($uid),
        'insights'  => expense_insights($uid),
        'charts'    => expense_chart_data($uid),
        'expenses'  => $stmt->fetchAll(),
    ];
}

function collect_expense_input(): array
{
    $amount = $_POST['amount'] ?? '';
    if ($amount === '' || !is_numeric($amount)) respond(false, 'A valid amount is required.');
    $amount = round((float) $amount, 2);
    if ($amount <= 0) respond(false, 'Amount must be greater than zero.');
    if ($amount > 10000000) respond(false, 'Amount is too large.');

    $category = $_POST['category'] ?? 'Other';
    if (!in_array($category, ['Food', 'Travel', 'Education', 'Shopping', 'Entertainment', 'Bills', 'Other'], true)) $category = 'Other';

    $date = $_POST['expense_date'] ?? '';
    $f = DateTime::createFromFormat('Y-m-d', $date);
    if (!$f || $f->format('Y-m-d') !== $date) respond(false, 'Invalid expense date.');

    $description = trim($_POST['description'] ?? '');
    if (mb_strlen($description) > 255) $description = mb_substr($description, 0, 255);

    return ['amount' => $amount, 'category' => $category, 'description' => $description, 'expense_date' => $date];
}

switch ($action) {
    case 'create': {
        $d = collect_expense_input();
        $stmt = db()->prepare('INSERT INTO expenses (user_id, amount, category, description, expense_date) VALUES (?,?,?,?,?)');
        $stmt->execute([$uid, $d['amount'], $d['category'], $d['description'], $d['expense_date']]);
        sync_notifications($uid);
        respond(true, 'Expense added.', expense_payload($uid) + ['new_id' => (int) db()->lastInsertId()]);
        break;
    }
    case 'update': {
        fetch_expense($uid, $id);
        $d = collect_expense_input();
        $stmt = db()->prepare('UPDATE expenses SET amount=?, category=?, description=?, expense_date=? WHERE id=? AND user_id=?');
        $stmt->execute([$d['amount'], $d['category'], $d['description'], $d['expense_date'], $id, $uid]);
        sync_notifications($uid);
        respond(true, 'Expense updated.', expense_payload($uid));
        break;
    }
    case 'delete': {
        fetch_expense($uid, $id);
        db()->prepare('DELETE FROM expenses WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        sync_notifications($uid);
        respond(true, 'Expense deleted.', expense_payload($uid));
        break;
    }
    default:
        respond(false, 'Unknown action.', [], 400);
}
