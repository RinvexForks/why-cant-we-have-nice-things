<?php
namespace History\Services\StatisticsComputer;

use History\Entities\Models\Question;
use History\Entities\Models\Request;
use History\Entities\Models\User;
use History\Entities\Models\Vote;

class StatisticsComputer
{
    /**
     * @param User $user
     *
     * @return array
     */
    public function forUser(User $user)
    {
        $totalVotes = $user->votes->count();
        $yesVotes   = $user->votes->filter(function (Vote $vote) {
            return $vote->choice < $vote->question->choices;
        })->count();
        $noVotes = $totalVotes - $yesVotes;

        $hivemind = $this->computeHivemind($user);

        return [
            'yes_votes'   => $yesVotes,
            'no_votes'    => $noVotes,
            'total_votes' => $totalVotes,
            'approval'    => $totalVotes ? $yesVotes / $totalVotes : 0,
            'hivemind'    => $hivemind,
        ];
    }

    /**
     * @param Question $question
     *
     * @return array
     */
    public function forQuestion(Question $question)
    {
        $approval = $question->votes->map(function (Vote $vote) use ($question) {
            return $vote->choice < $question->choices;
        });

        // Compute approval %
        if (!$approval->count()) {
            $approval = 0;
        } else {
            $approval = $approval->sum() / $approval->count();
        }

        return [
            'approval' => $approval,
            'passed'   => $question->hasPassed($approval),
        ];
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function forRequest(Request $request)
    {
        $approvals = $request->questions->map(function (Question $question) {
            return $question->approval ?: $this->forQuestion($question)['approval'];
        });

        $approval = $approvals->average();

        return [
            'approval' => $approval,
            'passed'   => $request->hasPassed($approval),
        ];
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * @param User $user
     *
     * @return float
     */
    protected function computeHivemind(User $user)
    {
        if ($user->votes->isEmpty()) {
            return 0;
        }

        // Did the user pick the majority's choice
        $hivemind = [];
        foreach ($user->votes as $vote) {
            $hivemind[] = $vote->question->majority_choice === $vote->choice;
        }

        // Compute number of correct choices over total questions
        $hivemind = count(array_filter($hivemind)) / count($hivemind);

        return $hivemind;
    }
}
