<?php declare(strict_types=1);

namespace Nadybot\User\Modules\DICT_MODULE;

require_once __DIR__ . '/vendor/autoload.php';

use AL\PhpWndb\{
	DiContainerFactory,
	WordNet,
	Model\Synsets\SynsetInterface,
};
use Nadybot\Core\{
	CommandReply,
	Nadybot,
	Text,
};

/**
 * @author Nadyita (RK5) <nadyita@hodorraid.org>
 *
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'dict',
 *		accessLevel = 'all',
 *		description = 'Look up the definition of a word',
 *		help        = 'dict.txt'
 *	)
 */
class DictController {

	/**
	 * Name of the module.
	 * Set automatically by module loader.
	 * @var string $moduleName
	 */
	public string $moduleName;

	/** @Inject */
	public Nadybot $chatBot;

	/** @Inject */
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
	 * Command to look up dictionary entries
	 *
	 * @param string                     $message The full command received
	 * @param string                     $channel Where did the command come from (tell, guild, priv)
	 * @param string                     $sender  The name of the user issuing the command
	 * @param \Nadybot\Core\CommandReply $sendto  Object to use to reply to
	 * @param string[]                   $args    The arguments to the dict-command
	 * @return void
	 *
	 * @HandlesCommand("dict")
	 * @Matches("/^dict\s+(.+)$/i")
	 */
	public function dictCommand(string $message, string $channel, string $sender, CommandReply $sendto, array $args): void {
		$containerFactory = new DiContainerFactory();
		$container = $containerFactory->createContainer();

		/** @var \AL\PhpWndb\WordNet */
		$wordNet = $container->get(WordNet::class);

		/** @var \AL\PhpWndb\Model\Synsets\SynsetInterface[] */
		$synsets = $wordNet->searchLemma($args[1]);

		if (empty($synsets)) {
			$msg = "No definition found for <highlight>$args[1]<end>.";
			$sendto->reply($msg);
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
			$blob .= "\n" . $this->getSynsetText($synset, $args[1]);
		}
		$blob .= "\n\n\nDictionary data provided by Princeton University";
		$msg = $this->text->makeBlob(
			"Found " . count($synsets) . " definition".
			(count($synsets) > 1 ? 's' : '').
			" for {$args[1]}",
			$blob
		);
		$sendto->reply($msg);
	}
}
