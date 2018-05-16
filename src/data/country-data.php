<?hh // strict

require_once ($_SERVER['DOCUMENT_ROOT'].'/../vendor/autoload.php');

class CountryDataController extends DataController {
  public async function genGenerateData(): Awaitable<void> {

    /* HH_IGNORE_ERROR[1002] */
    SessionUtils::sessionStart();
    SessionUtils::enforceLogin();

    list($my_team, $gameboard, $all_active_levels) = await \HH\Asio\va(
      MultiTeam::genTeam(SessionUtils::sessionTeam()),
      Configuration::gen('gameboard'),
      Level::genAllActiveLevels(),
    );

    $countries_data = (object) array();

    // If gameboard refresing is disabled, exit
    if ($gameboard->getValue() === '0') {
      $this->jsonSend($countries_data);
      exit(1);
    }

    foreach ($all_active_levels as $level) {
      $awaitables = Map {
        'country' => Country::gen(intval($level->getEntityId())),
        'category' => Category::genSingleCategory($level->getCategoryId()),
        'attachments_list' => Attachment::genAllAttachmentsFileNamesLinks(
          $level->getId(),
        ),
        'links_list' => Link::genAllLinksValues($level->getId()),
        'completed_by' => MultiTeam::genCompletedLevelTeamNames(
          $level->getId(),
        ),
      };
      $awaitables_results = await \HH\Asio\m($awaitables); // TODO: Combine Awaits

      $country = $awaitables_results['country'];
      $category = $awaitables_results['category'];
      $attachments_list = $awaitables_results['attachments_list'];
      $links_list = $awaitables_results['links_list'];
      $completed_by = $awaitables_results['completed_by'];

      invariant(
        $country instanceof Country,
        'country should be of type Country',
      );
      invariant(
        $category instanceof Category,
        'category should be of type Category',
      );

      if (!$country) {
        continue;
      }

      if ($level->getHint() !== '') {
        // There is hint, can this team afford it?
        if ($level->getPenalty() > $my_team->getPoints()) { // Not enough points
          $hint_cost = -2;
          $hint = 'no';
        } else {
          list($hint, $score) = await \HH\Asio\va(
            HintLog::genPreviousHint(
              $level->getId(),
              $my_team->getId(),
              false,
            ),
            ScoreLog::genPreviousScore(
              $level->getId(),
              $my_team->getId(),
              false,
            ),
          ); // TODO: Combine Awaits

          // Has this team requested this hint or scored this level before?
          if ($hint || $score) {
            $hint_cost = 0;
          }
          // Has this team scored this level before?
          if ($score) {
            $hint_cost = 0;
          }
          $hint = ($hint_cost === 0) ? $level->getHint() : 'yes';
        }
      } else { // No hints
        $hint_cost = -1;
        $hint = 'no';
      }

      // Who is the first owner of this level
      if ($completed_by) {
        $owner = await MultiTeam::genFirstCapture($level->getId()); // TODO: Combine Awaits
        $owner = $owner->getName();
      } else {
        $owner = 'Uncaptured';
      }

      //All possible Answer choices for this question
      $choiceA = "";
      $choiceB = "";
      $choiceC = "";
      $choiceD = "";
      if($level->getIsShortAnswer()){
        $choiceA = "Short Answer";
        $choiceB = "Short Answer";
        $choiceC = "Short Answer";
        $choiceD = "Short Answer";
      }
      else{
        $random = mt_rand(0,3);
        $choiceA = $level->getAnswerChoice1();
        $choiceB = $level->getAnswerChoice2();
        $choiceC = $level->getAnswerChoice3();
        $choiceD = $level->getAnswerChoice4();
      }

      //randomize order
      $choices = array($choiceA,$choiceB,$choiceC,$choiceD);
      shuffle($choices);

      $country_data = (object) array(
        'level_id' => $level->getId(),
        'title' => $level->getTitle(),
        'intro' => $level->getDescription(),
        'type' => $level->getType(),
        'points' => $points,
        'bonus' => $level->getBonus(),
        'category' => $category->getCategory(),
        'owner' => $owner,
        'completed' => $completed_by,
        'hint' => $hint,
        'hint_cost' => $hint_cost,
        'attachments' => $attachments_list,
        'links' => $links_list,
        'wrong_answer_penalty' => $wrong_answer_penalty,
        'numIncorrectGuesses' => $numIncorrectGuesses,
        'isShortAnswer' => $level->getIsShortAnswer(),
        'shuffledChoiceA' => $choices[0],
        'shuffledChoiceB' => $choices[1],
        'shuffledChoiceC' => $choices[2],
        'shuffledChoiceD' => $choices[3],
        'choiceA' => $choiceA,
        'choiceB' => $choiceB,
        'choiceC' => $choiceC,
        'choiceD' => $choiceD,
      );
      /* HH_FIXME[1002] */
      /* HH_FIXME[2011] */
      $countries_data->{$country->getName()} = $country_data;
    }

    $this->jsonSend($countries_data);
  }
}

$countryData = new CountryDataController();
$countryData->sendData();
