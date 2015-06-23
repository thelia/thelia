<?php

$pdo = $database->getConnection();

// Get locale
$locale = 'fr_FR';

$sqlGetLocale = "SELECT `locale` FROM `lang` WHERE `by_default` = :by_default";
$stmtGetLocale = $pdo->prepare($sqlGetLocale);
$stmtGetLocale->execute([':by_default' => 1]);
$resultLocales = $stmtGetLocale->fetchAll();

foreach ($resultLocales as $defaultLocale) {
    $locale = $defaultLocale['locale'];
}

// Get id, feature_id and free_text_value from feature_product
$sqlGetFeatureProduct = "SELECT `id`, `feature_id`, `free_text_value` FROM `feature_product` WHERE `feature_av_id` IS NULL";
$stmtGetFeatureProduct = $pdo->prepare($sqlGetFeatureProduct);
$stmtGetFeatureProduct->execute([':feature_av_id' => NULL]);

while ($featureProduct = $stmtGetFeatureProduct->fetch(PDO::FETCH_ASSOC)) {

    // Create new feature_av with the feature_id
    $sqlCreateFeatureAv = "INSERT INTO `feature_av` (feature_id, `position`) VALUES (:feature_id, :feature_position)";
    $stmtCreateFeatureAv = $pdo->prepare($sqlCreateFeatureAv);
    $stmtCreateFeatureAv->execute([':feature_id' => $featureProduct['feature_id'], ':feature_position' => 1]);

    // Get id from created feature_av
    $createdFeatureAvId = $pdo->lastInsertId();

    // Create new feature_av_i18n
    $sqlCreateFeatureAvI18n = "INSERT INTO `feature_av_i18n` (id, locale, title) VALUES (:id, :locale, :title)";
    $stmtCreateFeatureAvI18n = $pdo->prepare($sqlCreateFeatureAvI18n);
    $stmtCreateFeatureAvI18n->execute([':id' => $createdFeatureAvId, ':locale' => $locale, ':title' => $featureProduct['free_text_value']]);

    // Update old NULL feature_av_id and textual free_text_value values from feature_product
    $sqlUpdateFeatureProduct = "UPDATE feature_product SET feature_av_id = :feature_av_id, free_text_value = :free_text_value WHERE id = :featureProductId";
    $stmtUpdateFeatureProduct = $pdo->prepare($sqlUpdateFeatureProduct);
    $stmtUpdateFeatureProduct->execute([':feature_av_id' => $createdFeatureAvId, ':free_text_value' => 1, ':featureProductId' => $featureProduct['id']]);
}

