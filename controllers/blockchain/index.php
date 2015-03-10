<?php
	$numBlocks = $abe->getNumBlocks();
	$paginator = new Paginator(20, $numBlocks, true);
	$viewdata['paginator'] = $paginator->getAll();
	$limits = $viewdata['paginator']['current'];
	
	$blocks = $abe->getBlocksByHeight($limits['start'], $limits['stop']);

	foreach($blocks as $key => &$block) {
		$block['height'] = createLink(Type::Block, $block['height'], number_format($block['height']));
	}
	
	$viewdata['blocks'] = $blocks;
	$pagedata['view'] = $m->render('blockchain/index', $viewdata);