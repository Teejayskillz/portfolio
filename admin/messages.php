<?php
/**
 * Contact messages inbox.
 */

declare(strict_types=1);

require __DIR__ . '/_init.php';

require_admin();

$search = trim(query_string('q'));
$pageNumber = max(1, to_int(query_string('page')) ?: 1);
$perPage = 20;

$result = messages_page($pageNumber, $perPage, $search);
$messages = $result['rows'];
$total = $result['total'];
$pageCount = max(1, (int) ceil($total / $perPage));

$failed = (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE mail_status = 'failed'");

$page = [
    'title'    => 'Messages',
    'subtitle' => $total . ' messages · ' . $failed . ' with failed delivery',
    'active'   => 'messages',
];

admin_header($page);
?>

<div class="page-head">
    <div>
        <h2 class="page-head__title">Contact messages</h2>
        <p class="page-head__sub">
            Every submission is stored here — even when the mail server refuses delivery, so nothing is lost.
        </p>
    </div>
    <div class="page-head__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('email.php')) ?>"><?= icon('mail', 'icon', 14) ?> Email settings</a>
    </div>
</div>

<?php if ($failed > 0): ?>
    <div class="alert alert--warning" role="status">
        <span class="alert__icon" aria-hidden="true"></span>
        <p><?= e((string) $failed) ?> message(s) could not be emailed. Read and reply to them here.</p>
    </div>
<?php endif; ?>

<section class="panel">
    <form class="toolbar" method="get" action="<?= e(admin_url('messages.php')) ?>">
        <div class="toolbar__group">
            <label class="toolbar__search">
                <span class="visually-hidden">Search messages</span>
                <?= icon('search', 'icon', 16) ?>
                <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, email or subject…" maxlength="120">
            </label>
            <button class="btn btn--ghost btn--sm" type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a class="btn btn--ghost btn--sm" href="<?= e(admin_url('messages.php')) ?>">Reset</a>
            <?php endif; ?>
        </div>
        <p class="panel__hint"><?= e((string) $total) ?> message<?= $total === 1 ? '' : 's' ?></p>
    </form>

    <?php if ($messages === []): ?>
        <div class="panel__body">
            <?php empty_state(
                $search !== '' ? 'No matching messages' : 'No messages yet',
                $search !== '' ? 'Try a different search term.' : 'Messages sent through the contact form will appear here.',
                $search !== '' ? 'Clear search' : '',
                $search !== '' ? admin_url('messages.php') : ''
            ); ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead>
                    <tr>
                        <th scope="col">From</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Status</th>
                        <th scope="col">Received</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $message): ?>
                        <?php
                        $messageId = (int) $message['id'];
                        $url = admin_url('message.php?id=' . $messageId);
                        $whatsappValue = trim((string) ($message['whatsapp'] ?? ''));
                        $whatsappLink = $whatsappValue !== '' ? whatsapp_contact_url($whatsappValue) : '';
                        ?>
                        <tr>
                            <td data-label="From">
                                <a class="table__title" href="<?= e($url) ?>"><?= e((string) $message['name']) ?></a>
                                <span class="table__meta"><?= e((string) $message['email']) ?></span>
                                <?php if ($whatsappLink !== ''): ?>
                                    <a class="table__meta" href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener noreferrer"
                                       title="Open WhatsApp chat">
                                        <?= icon('whatsapp', 'icon', 12) ?> <?= e($whatsappValue) ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td data-label="Subject">
                                <a href="<?= e($url) ?>" style="color: var(--text-soft)"><?= e(truncate_text((string) $message['subject'], 60)) ?></a>
                            </td>
                            <td data-label="Status"><?= admin_message_badge((string) $message['mail_status']) ?></td>
                            <td data-label="Received"><?= e(time_ago((string) $message['created_at'])) ?></td>
                            <td data-label="Actions">
                                <div class="table__actions">
                                    <a class="icon-btn" href="<?= e($url) ?>" title="Read message" aria-label="Read message from <?= e((string) $message['name']) ?>">
                                        <?= icon('eye', 'icon', 15) ?>
                                    </a>
                                    <form method="post" action="<?= e(admin_url('message.php')) ?>"
                                          data-confirm="Delete this message permanently?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= e((string) $messageId) ?>">
                                        <button class="icon-btn icon-btn--danger" type="submit" title="Delete message"
                                                aria-label="Delete message from <?= e((string) $message['name']) ?>">
                                            <?= icon('trash', 'icon', 15) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel__body">
            <?php admin_pagination($pageNumber, $pageCount, admin_url('messages.php'), $search !== '' ? ['q' => $search] : []); ?>
        </div>
    <?php endif; ?>
</section>

<?php admin_footer(); ?>
