<?php declare(strict_types=1);

namespace Nadybot\User\Modules\DICT_MODULE;

require_once __DIR__ . '/vendor/autoload.php';

use AL\PhpWndb\{
	DiContainerFactory,
	WordNet,
	Model\Synsets\SynsetInterface,
};
use Nadybot\Core\{
	Attributes as NCA,
	CmdContext,
	ModuleInstance,
	Text,
};

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 */
#[
	NCA\Instance,
	NCA\DefineCommand(
		command:     'dict',
		accessLevel: 'guest',
		description: 'Look up the definition of a word',
	)
]
class DictController extends ModuleInstance {
	#[NCA\Inject]
	public Text $text;

	protected function getSynsetText(SynsetInterface $synset, string $search): string {
		$indent = "\n<black>______<end>";
		$blob = "<black>____<end><highlight>*<end><black>_<end>".
			wordwrap(ucfirst($synset->getGloss()), 80, "${indent}");
		$synonyms = array_map(
			function($word) {
				return $this->text->makeChatcmd($word, "/tell <myname> dict $word");
			},
			array_filter(
				array_map(
					function($word) {
						return $word->getLemma();
					},
					$synset->getWords()
				),
				function($word) use ($search) {
					return strtolower($word) !== strtolower($search);
				}
			)
		);
		if (count($synonyms) > 0) {
			$blob .= "${indent}See also: " . join(', ', $synonyms);
		}
		return $blob;
	}

	/**
	 * Look up a word in the dictionary
	 */
	#[NCA\HandlesCommand("dict")]
	public function dictCommand(CmdContext $context, string $term): void {
		$containerFactory = new DiContainerFactory();
		$container = $containerFactory->createContainer();

		/** @var \AL\PhpWndb\WordNet */
		$wordNet = $container->get(WordNet::class);

		/** @var \AL\PhpWndb\Model\Synsets\SynsetInterface[] */
		$synsets = $wordNet->searchLemma($term);

		if (empty($synsets)) {
			$msg = "No definition found for <highlight>{$term}<end>.";
			$context->reply($msg);
			return;
		}

		$blob = '';
		$lastType = null;
		foreach ($synsets as $synset) {
			if ($lastType !== $synset->getPartOfSpeech()) {
				$blob .= "\n\n<pagebreak><header2>" . ($lastType = $synset->getPartOfSpeech()) . "<end>";
			} else {
				$blob .= "\n";
			}
			$blob .= "\n" . $this->getSynsetText($synset, $term);
		}
		$blob .= "\n\n\n<i>Dictionary data provided by Princeton University</i>";
		$msg = $this->text->makeBlob(
			"Found " . count($synsets) . " definition".
			(count($synsets) > 1 ? 's' : '').
			" for {$term}",
			$blob
		);
		$context->reply($msg);
	}
}
