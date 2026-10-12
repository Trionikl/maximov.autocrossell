<?php

/**
 * AutoCrossell Recommendations Template
 * Универсальный шаблон, совместимый с темой bootstrap_v4
 *
 * @package maximov.autocrossell
 
 * @author Denis Maximov
 * @link https://marketplace.1c-bitrix.ru/solutions/maximov.autocrossell/
 * @license GPL-3.0-or-later
 */

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
	die();
}

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;

Loc::loadMessages(__FILE__);

if (!empty($arResult['error_message'])): ?>
	<div class="autocrossell-recommendations">
		<div class="error-msg"><?= $arResult['error_message'] ?></div>
	</div>
<?php
	return;
endif;

if (empty($arResult['products'])) {
	return;
}

$docRoot = \Bitrix\Main\Application::getDocumentRoot();
$customTitle = trim((string)($arParams['~TITLE'] ?? ''));
if ($customTitle !== '') {
	$customTitle = mb_substr($customTitle, 0, 150);
}
?>

<!-- Уникальная обёртка для изоляции от темы сайта -->
<div class="row mb-5 autocrossell-recommendations">
	<div class="col" data-entity="parent-container">

		<!-- Заголовок блока -->
		<div class="catalog-block-header autocrossell-recommendations__title" data-entity="header">
			<?php if ($customTitle !== ''): ?>
				<?= HtmlFilter::encode($customTitle) ?>
			<?php else: ?>
				<?= mb_substr((string)Loc::getMessage('AUTOCROSSELL_TITLE'), 0, 150) ?>
			<?php endif; ?>
		</div>

		<!-- Контейнер товаров (наследует сетку и цвета темы) -->
		<div class="catalog-products-crossell bx-blue" data-entity="catalog-products-crossell-container">
			<div class="row product-item-list-col-4" data-entity="items-row">

				<?php foreach ($arResult['products'] as $product): ?>
				<div class="col-6 col-md-3 product-item-small-card">

					<!-- Карточка товара -->
					<div class="product-item-container position-relative" id="autocrossell-item-<?= $product['id'] ?>" data-entity="item">
						<div class="product-item">

							<!-- Область изображения (клик покрывает stretched-link) -->
							<span class="product-item-image-wrapper" data-entity="image-wrapper">
								<?php
								$imgUrl = '';
								if (!empty($product['preview_picture']) && file_exists($docRoot . $product['preview_picture'])) {
									$imgUrl = $product['preview_picture'];
								} elseif (!empty($product['detail_picture']) && file_exists($docRoot . $product['detail_picture'])) {
									$imgUrl = $product['detail_picture'];
								} else {
									$imgUrl = '/bitrix/components/bitrix/catalog.products.viewed/templates/bootstrap_v4/images/no_photo.png';
								}
								?>
								<span class="product-item-image-slide item active"
									style="background-image: url('<?= htmlspecialcharsbx($imgUrl) ?>');">
								</span>
							</span>

							<!-- Название (единственная ссылка карточки, stretched-link) -->
							<h3 class="product-item-title">
								<a class="stretched-link" href="<?= htmlspecialcharsbx($product['url']) ?>"
									title="<?= htmlspecialcharsbx($product['name']) ?>">
									<?= htmlspecialcharsbx($product['name']) ?>
								</a>
							</h3>

							<!-- Цена (наследует форматирование темы) -->
							<?php if (!empty($product['price']) && !empty($product['price']['formatted'])): ?>
							<div class="product-item-info-container product-item-price-container"
								data-entity="price-block">
								<span class="product-item-price-current">
									<?= $product['price']['formatted'] ?>
								</span>
							</div>
							<?php endif; ?>

							<!-- Кнопка (визуальная, клик перехватывает stretched-link) -->
							<div class="product-item-info-container product-item-hidden" data-entity="buttons-block">
								<div class="product-item-button-container">
									<span class="btn btn-link btn-sm">
										<?= GetMessage('AUTOCROSSELL_BUY_BUTTON') ?>
									</span>
								</div>
							</div>

						</div>
					</div>
				</div>
				<?php endforeach; ?>

			</div>
		</div>
	</div>
</div>
