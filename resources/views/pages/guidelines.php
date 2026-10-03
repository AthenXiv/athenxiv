<?php if (!defined('ATHENAEUM_BOOTSTRAPPED')) { http_response_code(404); exit; } ?>
<?php
/**
 * Submission guidelines.
 *
 * @var string $maxPdf,$maxArchive,$contact,$extensions
 */
?>
<div class="container">
  <article class="prose-page">
    <h1><?= e(__('page.guidelines_title')) ?></h1>
    <p><?= e(__('page.guidelines_p1')) ?></p>

    <h2><?= e(__('page.guidelines_formats')) ?></h2>
    <ul>
      <li><?= e(__('page.guidelines_format_pdf')) ?></li>
      <li><?= e(__('page.guidelines_format_abstract')) ?></li>
      <li><?= e(__('page.guidelines_format_authors')) ?></li>
      <li><?= e(__('page.guidelines_format_attachments', [
          'extensions' => $extensions,
          'archive'    => $maxArchive,
          'count'      => \Athenaeum\Core\Settings::int('upload.max_attachments', 5),
      ])) ?></li>
      <li><?= e(__('page.guidelines_format_links')) ?></li>
    </ul>

    <h2><?= e(__('paper.pdf_limit')) ?></h2>
    <p><?= e(__('page.guidelines_limits', ['pdf' => $maxPdf, 'contact' => $contact])) ?></p>

    <h2><?= e(__('paper.withdraw')) ?></h2>
    <p><?= e(__('page.guidelines_withdraw')) ?></p>

    <h2><?= e(__('auth.terms')) ?></h2>
    <p><?= e(__('page.guidelines_rights')) ?></p>

    <p>
      <a class="btn btn--primary" href="<?= e(url('paper.create')) ?>"><?= e(__('paper.submit_title')) ?></a>
    </p>
  </article>
</div>
