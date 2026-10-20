<?php
/** 分支/标签切换器（JS 跳转）
 *  @var array $branches, $tags, $defaultRef, $repo, $current, $switchBase
 */
$current = $current ?? ($defaultRef ?? 'main');
$switchBase = $switchBase ?? repo_url($repo, '/tree/');
$options = [];
foreach ($branches as $branch) {
    $options[] = ['name' => (string)$branch['name'], 'type' => 'branch'];
}
foreach ($tags as $tag) {
    $options[] = ['name' => (string)$tag['name'], 'type' => 'tag'];
}
?>
<div class="ref-select">
  <?= icon('branch', 14) ?>
  <select onchange="if(this.value) location.href='<?= e($switchBase) ?>' + encodeURIComponent(this.value)" aria-label="切换分支或标签">
    <?php foreach ($options as $opt): ?>
      <option value="<?= e($opt['name']) ?>" <?= $opt['name'] === $current ? 'selected' : '' ?>>
        <?= e($opt['name']) ?><?= $opt['type'] === 'tag' ? '（标签）' : '' ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
