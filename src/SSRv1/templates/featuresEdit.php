<?php
/** @var \WEEEOpen\Tarallo\Feature[] $features */
?>

<?php
if (count($features) > 0) :
	$ultras = $this->getUltraFeatures($features);
	foreach ($ultras as $ultra) {
		// Names of all the ultraFeatures to insert
		$ultraNames[$ultra->name] = true;
	}
	$groups = $this->getGroupedFeatures($ultras);

	foreach ($groups as $groupTitle => $group) : ?>
	<section>
		<h5><?=$groupTitle?></h5>
		<ul>
			<?php foreach ($group as $ultra) : /** @var $ultra \WEEEOpen\Tarallo\SSRv1\UltraFeature */
				$help = $this->printExplanation($ultra);
				if ($help !== '') {
					$help = $this->e($help);
					$help = "<i class=\"fa fa-question-circle ml-1\" data-tippy-content=\"$help\"></i>";
				}

				?><li class="feature-edit-<?= $ultra->name ?> feature-edit pr-4">
					<div class="name"><label for="feature-el-<?= $ultra->name ?>"><?=$ultra->pname?><?=$help?></label></div>
					<?php switch ($ultra->type) :
						case WEEEOpen\Tarallo\BaseFeature::ENUM:
							?>
						<select class="value" autocomplete="off" data-internal-name="<?= $ultra->name ?>" data-internal-type="e" data-initial-value="<?= $this->e($ultra->value, 'asTextContent')?>" id="feature-el-<?= $ultra->name ?>">
													<?php if ($ultra->value == null) :
														?><option value="" disabled selected></option><?php
													endif; ?>
							<?php foreach ($this->getOptions($ultra->name) as $optionValue => $optionName) : ?>
							<option value="<?= $optionValue ?>" <?= $optionValue === $ultra->value ? 'selected' : '' ?>><?=$this->e($optionName)?></option>
							<?php endforeach ?>
						</select>
							<?php
							break; default:
						case WEEEOpen\Tarallo\BaseFeature::STRING:
							?>
						<div class="value" data-internal-type="s" data-internal-name="<?= $ultra->name ?>" data-initial-value="<?= $this->e($ultra->value) ?>" id="feature-el-<?= $ultra->name ?>" contenteditable="true"><?=$this->contentEditableWrap($this->e($ultra->pvalue))?></div>
							<?php
									   break; case WEEEOpen\Tarallo\BaseFeature::INTEGER:
								?>
						<div class="value" data-internal-type="i" data-internal-name="<?= $ultra->name ?>" data-internal-value="<?= $ultra->value ?>" data-previous-value="<?= $ultra->value ?>" data-initial-value="<?= $ultra->value ?>" id="feature-el-<?= $ultra->name ?>" contenteditable="true"><?=$this->contentEditableWrap($this->e($ultra->pvalue))?></div>
								<?php
												  break; case WEEEOpen\Tarallo\BaseFeature::DOUBLE:
											?>
						<div class="value" data-internal-type="d" data-internal-name="<?= $ultra->name ?>" data-internal-value="<?= $ultra->value ?>" data-previous-value="<?= $ultra->value ?>" data-initial-value="<?= $ultra->value ?>" id="feature-el-<?= $ultra->name ?>" contenteditable="true"><?=$this->contentEditableWrap($this->e($ultra->pvalue))?></div>
					<?php endswitch; ?>
					<div class="controls"><button data-name="<?= $ultra->name ?>" class="btn btn-danger ml-2 delete" aria-roledescription="delete" tabindex="-1"><i class="fa fa-trash" role="img" aria-label="Delete"></i></button></div>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
		<?php
	endforeach;
endif;
?>
<section class="newfeatures pr-4">
	<h5>New features</h5>
	<ul></ul>
</section>
