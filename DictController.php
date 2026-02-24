<?php declare(strict_types=1);

namespace Nadybot\User\Modules\DICT_MODULE;

require_once __DIR__ . '/vendor/autoload.php';

use Nadybot\Core\{
	Attributes as NCA,
	CmdContext,
	ModuleInstance,
	Text,
	Types\AccessLevel,
};
use PhpWndb\Dataset\{
	Model\Data\SynsetType,
	Search\Crawl\SynsetCrawler,
	Search\Crawl\WordCrawler,
	WordNetProvider,
};

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 */
#[
	NCA\Instance,
	NCA\DefineCommand(
		command: 'dict',
		accessLevel: AccessLevel::Guest,
		description: 'Look up the definition of a word',
	)
]
class DictController extends ModuleInstance {
	#[NCA\Inject]
	public Text $text;

	/** Look up a word in the dictionary */
	#[NCA\HandlesCommand('dict')]
	public function dictCommand(CmdContext $context, string $term): void {
		$wordNet = (new WordNetProvider())->getWordNet();

		/** @var \ArrayIterator<SynsetCrawler> */
		$synsets = $wordNet->search($term);

		if ($synsets->count() === 0) {
			$msg = "No definition found for <highlight>{$term}<end>.";
			$context->reply($msg);
			return;
		}

		$blob = '';

		/** @var ?string */
		$lastType = null;
		foreach ($synsets as $synset) {
			if ($lastType !== $this->getSynsetType($synset->getType())) {
				$blob .= "\n\n<pagebreak><header2>" .
					($lastType = $this->getSynsetType($synset->getType())) . '<end>';
			} else {
				$blob .= "\n";
			}
			$blob .= "\n" . $this->getSynsetText($synset, $term);
		}
		$blob .= "\n\n\n<i>Dictionary data provided by Princeton University</i>";
		$msg = $this->text->makeBlob(
			'Found ' . count($synsets) . ' definition'.
			(count($synsets) > 1 ? 's' : '').
			" for {$term}",
			$blob
		);
		$context->reply($msg);
	}

	protected function getSynsetText(SynsetCrawler $synset, string $search): string {
		$indent = "\n<black>______<end>";
		$blob = '<black>____<end><highlight>*<end><black>_<end>'.
			wordwrap(ucfirst($synset->getGloss()), 80, "{$indent}");
		$seeAlso = [];
		foreach ($synset as $word) {
			/** @var WordCrawler $word */
			$seeAlso[] = $word->toString();
		}
		$synonyms = array_map(
			function (string $word): string {
				return $this->text->makeChatcmd($word, "/tell <myname> dict {$word}");
			},
			array_filter(
				$seeAlso,
				static function (string $word) use ($search): bool {
					return strtolower($word) !== strtolower($search)
						&& strtolower($word) !== strtolower($search.'(p)')
						&& strtolower($word) !== strtolower($search.'(a)');
				}
			)
		);
		if (count($synonyms) > 0) {
			$blob .= "{$indent}See also: " . implode(', ', $synonyms);
		}
		return $blob;
	}

	private function getSynsetType(SynsetType $type): string {
		return match ($type) {
			SynsetType::ADJECTIVE_SATELLITE => 'ADJECTIVE',
			default => $type->name,
		};
	}
}
