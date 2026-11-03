<?php
/**
 * Server-side validation.
 *
 * Every validator returns ['data' => normalised values, 'errors' => field => message].
 * Validators never touch the database for writes; they only check shape/ranges.
 */

declare(strict_types=1);

/** Trim and remove control characters while keeping newlines/tabs. */
function clean_text(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

    return trim($value);
}

function clean_single_line(string $value): string
{
    return trim(preg_replace('/\s+/u', ' ', clean_text($value)) ?? '');
}

/** @return list<string> password policy violations (empty = acceptable) */
function password_policy_errors(string $password, string $username = ''): array
{
    $errors = [];
    $min = (int) config('security.password_min', 10);

    if (mb_strlen($password) < $min) {
        $errors[] = 'Use at least ' . $min . ' characters.';
    }
    if (mb_strlen($password) > 200) {
        $errors[] = 'That password is too long (200 characters maximum).';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Include at least one letter and one number.';
    }
    if ($username !== '' && strcasecmp($password, $username) === 0) {
        $errors[] = 'The password cannot be the same as the username.';
    }

    $common = ['password', 'password1', 'admin123', '12345678', 'qwerty123', 'letmein123', 'iloveyou'];
    if (in_array(strtolower($password), $common, true)) {
        $errors[] = 'That password is too easy to guess.';
    }

    return $errors;
}

/**
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_login(array $input): array
{
    $username = clean_single_line((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $errors = [];

    if ($username === '') {
        $errors['username'] = 'Enter your username.';
    } elseif (mb_strlen($username) > 60) {
        $errors['username'] = 'That username is too long.';
    }

    if ($password === '') {
        $errors['password'] = 'Enter your password.';
    }

    return ['data' => ['username' => $username, 'password' => $password], 'errors' => $errors];
}

/**
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_password_change(array $input, string $username): array
{
    $current = (string) ($input['current_password'] ?? '');
    $new = (string) ($input['new_password'] ?? '');
    $confirm = (string) ($input['confirm_password'] ?? '');
    $errors = [];

    if ($current === '') {
        $errors['current_password'] = 'Enter your current password.';
    }

    foreach (password_policy_errors($new, $username) as $message) {
        $errors['new_password'] = $errors['new_password'] ?? $message;
    }

    if ($confirm === '') {
        $errors['confirm_password'] = 'Repeat the new password.';
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'The two new passwords do not match.';
    }

    if ($new !== '' && $current !== '' && hash_equals($current, $new)) {
        $errors['new_password'] = 'Choose a password different from the current one.';
    }

    return [
        'data' => [
            'current_password' => $current,
            'new_password'     => $new,
            'confirm_password' => $confirm,
        ],
        'errors' => $errors,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_contact(array $input): array
{
    $name = clean_single_line((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $whatsapp = clean_single_line((string) ($input['whatsapp'] ?? ''));
    $subject = clean_single_line((string) ($input['subject'] ?? ''));
    $message = clean_text((string) ($input['message'] ?? ''));
    $errors = [];

    if ($name === '') {
        $errors['name'] = 'Please tell me your name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = 'Your name should be between 2 and 100 characters.';
    }

    if ($email === '') {
        $errors['email'] = 'Please add an email address so I can reply.';
    } elseif (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'That email address does not look valid.';
    }

    if ($whatsapp !== '') {
        $digits = (string) preg_replace('/\D+/', '', $whatsapp);
        $allowedCharacters = preg_match('/^[+0-9()\s.\-]+$/', $whatsapp) === 1;

        if (mb_strlen($whatsapp) > 30 || !$allowedCharacters || mb_strlen($digits) < 7 || mb_strlen($digits) > 15) {
            $errors['whatsapp'] = 'Enter a valid WhatsApp number, including country code (Nigerian 0… numbers are accepted).';
        }
    }

    if ($subject === '') {
        $errors['subject'] = 'Add a short subject.';
    } elseif (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
        $errors['subject'] = 'The subject should be between 3 and 150 characters.';
    }

    if ($message === '') {
        $errors['message'] = 'Tell me a little about what you need.';
    } elseif (mb_strlen($message) < 20) {
        $errors['message'] = 'Please add a little more detail (at least 20 characters).';
    } elseif (mb_strlen($message) > 5000) {
        $errors['message'] = 'That message is too long (5000 characters maximum).';
    }

    return [
        'data' => [
            'name'     => $name,
            'email'    => $email,
            'whatsapp' => $whatsapp,
            'subject'  => $subject,
            'message'  => $message,
        ],
        'errors' => $errors,
    ];
}

/**
 * Validate a project create/update submission.
 *
 * Drafts only need a title. Publishing requires the content a visitor will see.
 *
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_project(array $input, ?int $projectId = null): array
{
    $title = clean_single_line((string) ($input['title'] ?? ''));
    $slugInput = clean_single_line((string) ($input['slug'] ?? ''));
    $short = clean_text((string) ($input['short_description'] ?? ''));
    $description = clean_text((string) ($input['description'] ?? ''));
    $problem = clean_text((string) ($input['problem'] ?? ''));
    $solution = clean_text((string) ($input['solution'] ?? ''));
    $categoryId = to_int($input['category_id'] ?? 0);
    $liveUrl = trim((string) ($input['live_url'] ?? ''));
    $githubUrl = trim((string) ($input['github_url'] ?? ''));
    $completion = trim((string) ($input['completion_date'] ?? ''));
    $status = (string) ($input['status'] ?? 'draft');
    $seoTitle = clean_single_line((string) ($input['seo_title'] ?? ''));
    $seoDescription = clean_text((string) ($input['seo_description'] ?? ''));
    $isFeatured = !empty($input['is_featured']);
    $technologies = $input['technologies'] ?? [];
    $errors = [];

    if ($title === '') {
        $errors['title'] = 'A project title is required.';
    } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 160) {
        $errors['title'] = 'The title should be between 2 and 160 characters.';
    }

    $slug = slugify($slugInput !== '' ? $slugInput : $title, 'project');
    if (mb_strlen($slug) > 80) {
        $slug = mb_substr($slug, 0, 80);
        $slug = rtrim($slug, '-');
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    if ($status === 'published') {
        if (mb_strlen($short) < 20) {
            $errors['short_description'] = 'Add a short description (at least 20 characters) before publishing.';
        }
        if (mb_strlen($description) >= 1 && mb_strlen($description) < 40) {
            $errors['description'] = 'The full description is too short to publish — write at least 40 characters.';
        }
        if ($description === '') {
            $errors['description'] = 'Add a full description before publishing.';
        }
        if ($categoryId <= 0) {
            $errors['category_id'] = 'Choose a project category before publishing.';
        }
    }

    if (mb_strlen($short) > 300) {
        $errors['short_description'] = 'Keep the short description under 300 characters.';
    }
    if (mb_strlen($description) > 12000) {
        $errors['description'] = 'The description is too long (12000 characters maximum).';
    }
    if (mb_strlen($problem) > 5000) {
        $errors['problem'] = 'The problem statement is too long (5000 characters maximum).';
    }
    if (mb_strlen($solution) > 5000) {
        $errors['solution'] = 'The solution text is too long (5000 characters maximum).';
    }

    if ($liveUrl !== '') {
        if (mb_strlen($liveUrl) > 500 || !is_safe_url($liveUrl)) {
            $errors['live_url'] = 'Enter a complete http:// or https:// URL for the live project.';
        }
    }
    if ($githubUrl !== '') {
        if (mb_strlen($githubUrl) > 500 || !is_safe_url($githubUrl)) {
            $errors['github_url'] = 'Enter a complete http:// or https:// URL for the source code.';
        }
    }

    if ($completion !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $completion);
        $valid = $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $completion;
        if (!$valid) {
            $errors['completion_date'] = 'Use a valid completion date (YYYY-MM-DD).';
        } elseif ($date > new DateTimeImmutable('+1 day')) {
            $errors['completion_date'] = 'The completion date cannot be in the future.';
        }
    }

    if (mb_strlen($seoTitle) > 160) {
        $errors['seo_title'] = 'Keep the SEO title under 160 characters.';
    }
    if (mb_strlen($seoDescription) > 300) {
        $errors['seo_description'] = 'Keep the SEO description under 300 characters.';
    }

    $techIds = [];
    if (is_array($technologies)) {
        foreach ($technologies as $value) {
            $id = to_int($value);
            if ($id > 0) {
                $techIds[$id] = $id;
            }
        }
    }

    return [
        'data' => [
            'title'             => $title,
            'slug'              => $slug,
            'short_description' => $short,
            'description'       => $description,
            'problem'           => $problem,
            'solution'          => $solution,
            'category_id'       => $categoryId,
            'live_url'          => $liveUrl !== '' ? $liveUrl : null,
            'github_url'        => $githubUrl !== '' ? $githubUrl : null,
            'completion_date'   => $completion !== '' ? $completion : null,
            'status'            => $status,
            'is_featured'       => $isFeatured ? 1 : 0,
            'seo_title'         => $seoTitle !== '' ? $seoTitle : null,
            'seo_description'   => $seoDescription !== '' ? $seoDescription : null,
            'technologies'      => array_values($techIds),
            'project_id'        => $projectId,
        ],
        'errors' => $errors,
    ];
}

/**
 * Validate the category/technology manager forms.
 *
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_taxonomy(array $input, string $type): array
{
    $name = clean_single_line((string) ($input['name'] ?? ''));
    $errors = [];

    if ($name === '') {
        $errors['name'] = 'Enter a name.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $errors['name'] = 'Use between 2 and 60 characters.';
    }

    $data = ['name' => $name, 'slug' => slugify($name), 'type' => $type];

    if ($type === 'technology') {
        $group = clean_single_line((string) ($input['group_name'] ?? 'Other'));
        if ($group === '' || mb_strlen($group) > 60) {
            $group = 'Other';
        }
        $data['group_name'] = $group;
    }

    if ($type === 'category') {
        $data['sort_order'] = to_int($input['sort_order'] ?? 0);
    }

    return ['data' => $data, 'errors' => $errors];
}

/**
 * Validate the dashboard email configuration.
 *
 * @param array<string, mixed> $input
 * @return array{data: array<string, mixed>, errors: array<string, string>}
 */
function validate_email_settings(array $input): array
{
    $to = clean_single_line((string) ($input['to'] ?? ''));
    $from = clean_single_line((string) ($input['from'] ?? ''));
    $fromName = clean_single_line((string) ($input['from_name'] ?? ''));
    $enabled = !empty($input['confirmation_enabled']);
    $subject = clean_single_line((string) ($input['confirmation_subject'] ?? ''));
    $body = clean_text((string) ($input['confirmation_body'] ?? ''));
    $errors = [];

    if ($to === '') {
        $errors['to'] = 'Enter the email address that should receive notifications.';
    } elseif (mb_strlen($to) > 190 || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        $errors['to'] = 'Enter a valid email address (for example you@gmail.com).';
    }

    if ($from === '') {
        $errors['from'] = 'Enter the address emails should be sent from.';
    } elseif (mb_strlen($from) > 190 || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        $errors['from'] = 'Enter a valid sender address (for example no-reply@yourdomain.com).';
    }

    if ($fromName === '') {
        $errors['from_name'] = 'Enter the sender name shown in inboxes.';
    } elseif (mb_strlen($fromName) < 2 || mb_strlen($fromName) > 100) {
        $errors['from_name'] = 'Use between 2 and 100 characters for the sender name.';
    }

    if ($subject === '') {
        $errors['confirmation_subject'] = 'Enter a subject for the visitor confirmation.';
    } elseif (mb_strlen($subject) < 3 || mb_strlen($subject) > 150) {
        $errors['confirmation_subject'] = 'Keep the confirmation subject between 3 and 150 characters.';
    }

    if ($body === '') {
        $errors['confirmation_body'] = 'Enter the confirmation message visitors should receive.';
    } elseif (mb_strlen($body) < 20) {
        $errors['confirmation_body'] = 'The confirmation message is too short (at least 20 characters).';
    } elseif (mb_strlen($body) > 5000) {
        $errors['confirmation_body'] = 'Keep the confirmation message under 5000 characters.';
    }

    return [
        'data' => [
            'to'                   => $to,
            'from'                 => $from,
            'from_name'            => $fromName,
            'confirmation_enabled' => $enabled ? 1 : 0,
            'confirmation_subject' => $subject,
            'confirmation_body'    => $body,
        ],
        'errors' => $errors,
    ];
}
