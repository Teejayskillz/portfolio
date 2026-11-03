<?php
/**
 * Home page.
 *
 * Expected variables:
 * @var list<array<string, mixed>> $featuredProjects
 * @var int                        $publishedTotal
 * @var array<string, string>      $formErrors
 * @var array<string, mixed>       $oldInput
 * @var array<string, mixed>       $meta
 */

if (!defined('APP_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

$owner = config('owner');
$whatsappHello = 'Hi Tijani, I would like to discuss a project.';
$error = static fn (string $field): string => field_error($formErrors, $field);
$fieldClass = static fn (string $field): string => $error($field) !== '' ? 'field field--error' : 'field';
$old = static fn (string $key): string => (string) ($oldInput[$key] ?? '');

site_header($meta);
?>

<?= alerts_html() ?>

<main id="main">
    <!-- Hero ---------------------------------------------------------- -->
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero__bg" aria-hidden="true">
            <span class="hero__grid"></span>
            <span class="hero__glow"></span>
        </div>

        <div class="container hero__inner">
            <div class="hero__content">
                <p class="hero__eyebrow">
                    <span class="status-dot" aria-hidden="true"></span>
                    <span><strong><?= e($owner['name']) ?></strong> &nbsp;·&nbsp; <?= e($owner['location']) ?></span>
                </p>

                <h1 class="hero__title" id="hero-title">
                    Building digital solutions that
                    <span class="accent-line">move businesses forward.</span>
                </h1>

                <p class="hero__lead">
                    I'm Tijani, a software and web developer. I build websites, custom web applications
                    and backend systems for businesses that need something that actually works — not just
                    something that looks finished.
                </p>

                <div class="hero__actions">
                    <a class="btn btn--primary btn--lg" href="<?= e(projects_url()) ?>">
                        View My Projects
                        <?= icon('arrow-up-right', 'icon', 17) ?>
                    </a>
                    <a class="btn btn--ghost btn--lg" href="<?= e(section_url('contact')) ?>">
                        Let's Work Together
                        <?= icon('message', 'icon', 17) ?>
                    </a>
                </div>

                <dl class="hero__meta">
                    <div class="hero-meta__item">
                        <dt class="hero-meta__label">Based in</dt>
                        <dd class="hero-meta__value"><?= e($owner['location']) ?></dd>
                    </div>
                    <div class="hero-meta__item">
                        <dt class="hero-meta__label">Focus</dt>
                        <dd class="hero-meta__value">Web apps &amp; backends</dd>
                    </div>
                    <div class="hero-meta__item">
                        <dt class="hero-meta__label">Working with</dt>
                        <dd class="hero-meta__value">Python · PHP · JavaScript</dd>
                    </div>
                    <div class="hero-meta__item">
                        <dt class="hero-meta__label">Availability</dt>
                        <dd class="hero-meta__value">Open to new projects</dd>
                    </div>
                </dl>
            </div>

            <div class="hero__visual" aria-hidden="true">
                <div class="code-card">
                    <div class="code-card__bar">
                        <span class="code-card__dots"><i></i><i></i><i></i></span>
                        <span class="code-card__file">projects/models.py</span>
                        <span class="code-card__badge">Python</span>
                    </div>
                    <pre class="code-card__code"><code><span class="c-comment"># the part that has to be right</span>
<span class="c-key">class</span> <span class="c-type">Project</span><span class="c-punc">(</span>models<span class="c-punc">.</span>Model<span class="c-punc">):</span>
    title <span class="c-punc">=</span> models<span class="c-punc">.</span><span class="c-fn">CharField</span><span class="c-punc">(</span>max_length<span class="c-punc">=</span><span class="c-num">160</span><span class="c-punc">)</span>
    slug <span class="c-punc">=</span> models<span class="c-punc">.</span><span class="c-fn">SlugField</span><span class="c-punc">(</span>unique<span class="c-punc">=</span><span class="c-key">True</span><span class="c-punc">)</span>
    stack <span class="c-punc">=</span> models<span class="c-punc">.</span><span class="c-fn">ManyToManyField</span><span class="c-punc">(</span><span class="c-type">Technology</span><span class="c-punc">)</span>
    published <span class="c-punc">=</span> models<span class="c-punc">.</span><span class="c-fn">BooleanField</span><span class="c-punc">(</span>default<span class="c-punc">=</span><span class="c-key">False</span><span class="c-punc">)</span>

    <span class="c-key">def</span> <span class="c-fn">__str__</span><span class="c-punc">(</span>self<span class="c-punc">):</span>
        <span class="c-key">return</span> self<span class="c-punc">.</span>title</code></pre>
                    <div class="code-card__foot">
                        <span>clean models</span>
                        <span>prepared SQL</span>
                        <span>tested before launch</span>
                    </div>
                </div>

                <span class="floating-chip floating-chip--one"><?= icon('code', 'icon', 15) ?> maintainable code</span>
                <span class="floating-chip floating-chip--two"><?= icon('shield', 'icon', 15) ?> secure by default</span>
            </div>
        </div>

        <div class="container">
            <div class="tech-marquee" aria-hidden="true">
                <div class="tech-marquee__track">
                    <?php for ($copy = 0; $copy < 2; $copy++): ?>
                        <div class="tech-marquee__group">
                            <?php foreach ($marqueeTechnologies as $technology): ?>
                                <span class="tech-marquee__item"><?= e($technology) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Selected work ------------------------------------------------- -->
    <section class="section" id="work" aria-labelledby="work-title">
        <div class="container">
            <div class="work__head">
                <header class="section-head" data-reveal>
                    <p class="eyebrow">Selected work</p>
                    <h2 class="section-head__title" id="work-title">
                        Projects built to <span class="accent">solve something</span>
                    </h2>
                    <p class="section-head__lead">
                        A short, honest selection of things I've built. Each project page explains what it
                        does, the problem behind it and the technical decisions involved.
                    </p>
                </header>
                <a class="link-arrow work__more" href="<?= e(projects_url()) ?>">
                    All projects<?= $publishedTotal > 0 ? ' (' . e((string) $publishedTotal) . ')' : '' ?>
                    <?= icon('arrow-right', 'icon', 16) ?>
                </a>
            </div>

            <?php if ($featuredProjects !== []): ?>
                <div class="project-grid">
                    <?php foreach ($featuredProjects as $index => $project): ?>
                        <div data-reveal style="--reveal-delay: <?= e((string) ($index * 70)) ?>ms">
                            <?php project_card($project); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php empty_state(
                    'Projects are being added',
                    'New work is being documented and will appear here. In the meantime, the GitHub profile has the code.',
                    'Visit GitHub',
                    (string) $owner['github']
                ); ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- About ---------------------------------------------------------- -->
    <section class="section section--surface" id="about" aria-labelledby="about-title">
        <div class="container about__grid">
            <div class="about__copy" data-reveal>
                <p class="eyebrow">About</p>
                <h2 class="section-head__title" id="about-title">
                    An independent developer, focused on <span class="accent">useful software</span>
                </h2>

                <p class="lede">
                    I'm Apatira Tijani Adewale, a software and web developer based in Lagos, Nigeria.
                    I build websites, custom web applications and backend systems for businesses.
                </p>

                <p>
                    I work directly with the people who use what I build, which usually means starting with
                    the awkward questions: what takes too long, what gets lost, what does the business need
                    to know that it currently can't see? The software comes after that.
                </p>
                <p>
                    My focus is on the parts a visitor never notices — data models that make sense,
                    validation that holds, predictable deployment, and a codebase the next developer can
                    read. I'd rather ship a focused feature that works than a long list that doesn't.
                </p>

                <div class="principles">
                    <div class="principle">
                        <span class="principle__icon"><?= icon('search', 'icon', 15) ?></span>
                        <div>
                            <h3 class="principle__title">Understand the problem first</h3>
                            <p class="principle__text">No feature list until the actual workflow and constraints are clear.</p>
                        </div>
                    </div>
                    <div class="principle">
                        <span class="principle__icon"><?= icon('layers', 'icon', 15) ?></span>
                        <div>
                            <h3 class="principle__title">Build for maintainability</h3>
                            <p class="principle__text">Simple structure, clear naming, no clever tricks that only work today.</p>
                        </div>
                    </div>
                    <div class="principle">
                        <span class="principle__icon"><?= icon('message', 'icon', 15) ?></span>
                        <div>
                            <h3 class="principle__title">Communicate plainly</h3>
                            <p class="principle__text">Progress you can see, decisions explained, no mystery invoices.</p>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="profile-card" data-reveal aria-label="Profile summary">
                <div class="profile-card__top">
                    <span class="profile-avatar" aria-hidden="true">TA</span>
                    <div>
                        <h2 class="profile-card__name"><?= e($owner['name']) ?></h2>
                        <p class="profile-card__role"><?= e($owner['role']) ?></p>
                    </div>
                </div>

                <div class="profile-card__rows">
                    <div class="profile-row">
                        <span class="profile-row__label">Location</span>
                        <span class="profile-row__value"><?= e($owner['location']) ?></span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-row__label">Builds</span>
                        <span class="profile-row__value">Websites, web apps, backends</span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-row__label">Primary stack</span>
                        <span class="profile-row__value">Python · Django · PHP</span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-row__label">Status</span>
                        <span class="profile-row__value">Available for projects</span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-row__label">Email</span>
                        <span class="profile-row__value">
                            <a href="<?= e(email_url('Project enquiry')) ?>"><?= e($owner['email']) ?></a>
                        </span>
                    </div>
                    <div class="profile-row">
                        <span class="profile-row__label">Code</span>
                        <span class="profile-row__value">
                            <a href="<?= e($owner['github']) ?>" target="_blank" rel="noopener noreferrer">github.com/Teejayskillz</a>
                        </span>
                    </div>
                </div>

                <div class="profile-card__foot">
                    <a class="btn btn--ghost" href="<?= e(whatsapp_url($whatsappHello)) ?>" target="_blank" rel="noopener noreferrer">
                        <?= icon('whatsapp', 'icon', 17) ?> Start a conversation
                    </a>
                </div>
            </aside>
        </div>
    </section>

    <!-- Services ------------------------------------------------------- -->
    <section class="section" id="services" aria-labelledby="services-title">
        <div class="container">
            <header class="section-head" data-reveal>
                <p class="eyebrow">Services</p>
                <h2 class="section-head__title" id="services-title">
                    What I can <span class="accent">build for you</span>
                </h2>
                <p class="section-head__lead">
                    Each of these starts from a business problem, not a technology. Here's what that work
                    looks like in practice.
                </p>
            </header>

            <?php
            $services = [
                [
                    'icon'  => 'layout',
                    'title' => 'Website Development',
                    'text'  => 'A fast, clear website that explains what your business does and turns visitors into enquiries. Designed to read well on a phone, load quickly and stay easy to update.',
                ],
                [
                    'icon'  => 'code',
                    'title' => 'Web Application Development',
                    'text'  => 'Custom tools that replace spreadsheets, long email threads and manual paperwork — dashboards, client portals, booking flows and internal systems built around how your team actually works.',
                ],
                [
                    'icon'  => 'server',
                    'title' => 'Backend Development (Python/Django)',
                    'text'  => 'The engine behind the product: data models, authentication, permissions, business rules and an admin area your team can use without asking a developer first.',
                ],
                [
                    'icon'  => 'cart',
                    'title' => 'E-commerce Development',
                    'text'  => 'Product catalogues, carts and checkout journeys that make buying straightforward, with the order and inventory information your team needs behind the scenes.',
                ],
                [
                    'icon'  => 'plug',
                    'title' => 'API Development and Integration',
                    'text'  => 'Connect your site to payments, messaging, CRMs and other services. Clean endpoints, sensible error handling and documentation so integrations stop being fragile.',
                ],
                [
                    'icon'  => 'zap',
                    'title' => 'Business Automation',
                    'text'  => 'Automate the repetitive work — reports, notifications, data entry, syncing between tools — so time goes into decisions instead of copying and pasting.',
                ],
                [
                    'icon'  => 'smartphone',
                    'title' => 'Mobile App Development',
                    'text'  => 'Mobile-first web apps and progressive web apps that feel quick and app-like for customers, without maintaining a separate codebase for every platform.',
                ],
            ];
            ?>

            <div class="services__grid">
                <?php foreach ($services as $index => $service): ?>
                    <article class="service-card" data-reveal style="--reveal-delay: <?= e((string) (($index % 3) * 60)) ?>ms">
                        <div class="service-card__top">
                            <span class="service-card__icon"><?= icon($service['icon'], 'icon', 20) ?></span>
                            <span class="service-card__num"><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        </div>
                        <h3 class="service-card__title"><?= e($service['title']) ?></h3>
                        <p class="service-card__text"><?= e($service['text']) ?></p>
                        <p class="service-card__foot">Scoped to your business</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Technology stack ---------------------------------------------- -->
    <section class="section section--surface" id="stack" aria-labelledby="stack-title">
        <div class="container">
            <header class="section-head" data-reveal>
                <p class="eyebrow">Technology</p>
                <h2 class="section-head__title" id="stack-title">
                    Tools chosen for the <span class="accent">job</span>
                </h2>
                <p class="section-head__lead">
                    I work across a deliberately practical stack. The choice depends on the problem:
                    Django for data-heavy applications, PHP where the host calls for it, vanilla
                    JavaScript and HTMX when a page doesn't need a framework at all.
                </p>
            </header>

            <?php
            $stackIcons = [
                'Backend & Frameworks' => 'server',
                'Frontend'             => 'code',
                'Databases'            => 'database',
                'CMS & Platforms'      => 'layers',
            ];
            ?>
            <div class="stack__grid">
                <?php $groupIndex = 0; ?>
                <?php foreach ($technologyGroups as $groupName => $groupTechnologies): ?>
                    <div class="stack-group" data-reveal style="--reveal-delay: <?= e((string) ($groupIndex * 70)) ?>ms">
                        <h3 class="stack-group__title">
                            <?= icon($stackIcons[$groupName] ?? 'layers', 'icon', 15) ?>
                            <?= e((string) $groupName) ?>
                        </h3>
                        <div class="stack-chips">
                            <?php foreach ($groupTechnologies as $technology): ?>
                                <span class="stack-chip"><?= e((string) $technology['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php $groupIndex++; ?>
                <?php endforeach; ?>
            </div>

            <p class="stack-note">
                No skill percentages here — they don't mean anything. What matters is whether the
                right tool was chosen and whether the result holds up in production.
            </p>
        </div>
    </section>

    <!-- Process -------------------------------------------------------- -->
    <section class="section" id="process" aria-labelledby="process-title">
        <div class="container">
            <header class="section-head section-head--center" data-reveal>
                <p class="eyebrow">How I work</p>
                <h2 class="section-head__title" id="process-title">
                    A calm, <span class="accent">predictable process</span>
                </h2>
                <p class="section-head__lead">
                    The same four stages on every project, scaled to the size of the job.
                </p>
            </header>

            <div class="process__grid">
                <?php
                $steps = [
                    ['title' => 'Discover', 'text' => 'We talk through the business, the users and the constraints. I write the scope down so we agree on what "done" means.'],
                    ['title' => 'Design', 'text' => 'Structure the content and the flows first, then the interface. You review a working shape before the heavy build starts.'],
                    ['title' => 'Build', 'text' => 'Implementation in small, reviewable steps on a link you can open. Data models, validation and edge cases are handled as we go.'],
                    ['title' => 'Launch & support', 'text' => 'Deploy, configure and hand over documentation. After launch I stay available for fixes and small improvements.'],
                ];
                ?>
                <?php foreach ($steps as $index => $step): ?>
                    <article class="process-step" data-reveal style="--reveal-delay: <?= e((string) ($index * 70)) ?>ms">
                        <span class="process-step__num">STEP <?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                        <h3 class="process-step__title"><?= e($step['title']) ?></h3>
                        <p class="process-step__text"><?= e($step['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact -------------------------------------------------------- -->
    <section class="section section--surface" id="contact" aria-labelledby="contact-title">
        <div class="container contact__grid">
            <div class="contact__panel" data-reveal>
                <p class="eyebrow">Contact</p>
                <h2 class="contact__title" id="contact-title">
                    Let's build something <span class="accent">useful</span>
                </h2>
                <p class="contact__text">
                    Tell me what you're trying to solve — a new website, a system that has outgrown its
                    spreadsheet, or an idea you want to test. I'll reply with honest questions and a clear
                    next step.
                </p>

                <ul class="contact-list">
                    <li>
                        <a class="contact-list__item" href="<?= e(whatsapp_url($whatsappHello)) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="contact-list__icon"><?= icon('whatsapp', 'icon', 18) ?></span>
                            <span class="contact-list__body">
                                <span class="contact-list__label">WhatsApp — fastest</span>
                                <span class="contact-list__value"><?= e($owner['phone_display']) ?></span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="contact-list__item" href="<?= e(email_url('Project enquiry')) ?>">
                            <span class="contact-list__icon"><?= icon('mail', 'icon', 18) ?></span>
                            <span class="contact-list__body">
                                <span class="contact-list__label">Email</span>
                                <span class="contact-list__value"><?= e($owner['email']) ?></span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="contact-list__item" href="<?= e($owner['github']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="contact-list__icon"><?= icon('github', 'icon', 18) ?></span>
                            <span class="contact-list__body">
                                <span class="contact-list__label">GitHub</span>
                                <span class="contact-list__value">github.com/Teejayskillz</span>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="contact-list__item" href="<?= e($owner['linkedin']) ?>" target="_blank" rel="noopener noreferrer">
                            <span class="contact-list__icon"><?= icon('linkedin', 'icon', 18) ?></span>
                            <span class="contact-list__body">
                                <span class="contact-list__label">LinkedIn</span>
                                <span class="contact-list__value">Tijani Apatira</span>
                            </span>
                        </a>
                    </li>
                </ul>

                <p class="contact__note">
                    <?= icon('map-pin', 'icon', 15) ?>
                    <span>Based in <?= e($owner['location']) ?>. Working with clients locally and remotely.</span>
                </p>
            </div>

            <div class="contact-form-card" data-reveal style="--reveal-delay: 90ms">
                <h3 class="contact-form-card__title">Send a message</h3>
                <p class="contact-form-card__sub">Fields marked with * are required. I read every message.</p>

                <?php if ($error('form') !== ''): ?>
                    <div class="alert alert--error" role="alert">
                        <span class="alert__icon" aria-hidden="true"></span>
                        <p><?= e($error('form')) ?></p>
                    </div>
                <?php endif; ?>

                <form class="contact-form" action="<?= e(public_url('contact.php')) ?>" method="post" data-enhance>
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_token" value="<?= e(contact_form_token()) ?>">

                    <div class="honeypot" aria-hidden="true">
                        <label for="contact-website">Website (leave this empty)</label>
                        <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-grid">
                        <div class="<?= e($fieldClass('name')) ?>">
                            <label for="contact-name">Name *</label>
                            <input type="text" id="contact-name" name="name" value="<?= e($old('name')) ?>"
                                   placeholder="Your name" required minlength="2" maxlength="100" autocomplete="name">
                            <?php if ($error('name') !== ''): ?>
                                <p class="field__error"><?= e($error('name')) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="<?= e($fieldClass('email')) ?>">
                            <label for="contact-email">Email *</label>
                            <input type="email" id="contact-email" name="email" value="<?= e($old('email')) ?>"
                                   placeholder="you@company.com" required maxlength="190" autocomplete="email">
                            <?php if ($error('email') !== ''): ?>
                                <p class="field__error"><?= e($error('email')) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="<?= e($fieldClass('subject')) ?>">
                            <label for="contact-subject">Subject *</label>
                            <input type="text" id="contact-subject" name="subject" value="<?= e($old('subject')) ?>"
                                   placeholder="What's this about?" required minlength="3" maxlength="150">
                            <?php if ($error('subject') !== ''): ?>
                                <p class="field__error"><?= e($error('subject')) ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="<?= e($fieldClass('whatsapp')) ?>">
                            <label for="contact-whatsapp">
                                WhatsApp
                                <span class="optional">optional</span>
                            </label>
                            <input type="tel" id="contact-whatsapp" name="whatsapp" value="<?= e($old('whatsapp')) ?>"
                                   placeholder="+234 803 123 4567" maxlength="30" inputmode="tel" autocomplete="tel">
                            <?php if ($error('whatsapp') !== ''): ?>
                                <p class="field__error"><?= e($error('whatsapp')) ?></p>
                            <?php else: ?>
                                <p class="field__hint">Optional. Include the country code, or a Nigerian number starting with 0.</p>
                            <?php endif; ?>
                        </div>

                        <div class="<?= e($fieldClass('message')) ?> field--full">
                            <label for="contact-message">Message *</label>
                            <textarea id="contact-message" name="message" rows="6" required minlength="20" maxlength="5000"
                                      data-counter="#message-count"
                                      placeholder="What are you building, and what's getting in the way?"><?= e($old('message')) ?></textarea>
                            <p class="field__hint" id="message-count" aria-live="polite"></p>
                            <?php if ($error('message') !== ''): ?>
                                <p class="field__error"><?= e($error('message')) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-footer">
                        <p class="form-note">
                            Your details are only used to reply to this message. No mailing list, no sharing.
                        </p>
                        <button class="btn btn--primary" type="submit">
                            Send Message
                            <?= icon('send', 'icon', 16) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>

<?php site_footer($meta); ?>
