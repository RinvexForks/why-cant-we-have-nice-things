<?php
namespace History\Services\StatisticsComputer;

use History\Entities\Models\Company;
use History\Entities\Models\Question;
use History\Entities\Models\Request;
use History\Entities\Models\User;
use History\Entities\Models\Vote;

class StatisticsComputer
{
    /**
     * @param Company $company
     *
     * @return array
     */
    public function forCompany(Company $company)
    {
        $totalUsers = User::count();

        return [
          'representation' => $company->users()->count() / $totalUsers,
        ];
    }

    /**
     * @param User $user
     *
     * @return array
     */
    public function forUser(User $user)
    {
        $totalVotes = $user->votes->count();
        $yesVotes   = $user->votes->filter(function (Vote $vote) {
            return $vote->isPositive();
        })->count();
        $noVotes = $totalVotes - $yesVotes;

        $hivemind        = $this->computeHivemind($user);
        $passedRequests  = $user->approvedRequests->count();
        $createdRequests = $user->requests->count();

        return [
            'yes_votes'   => $yesVotes,
            'no_votes'    => $noVotes,
            'total_votes' => $totalVotes,
            'success'     => $this->roundToFloatLimit($createdRequests ? $passedRequests / $createdRequests : 0),
            'approval'    => $this->roundToFloatLimit($totalVotes ? $yesVotes / $totalVotes : 0),
            'hivemind'    => $this->roundToFloatLimit($hivemind),
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
            return $vote->isPositive();
        });

        // Compute approval %
        if (!$approval->count()) {
            $approval = 0;
        } else {
            $approval = $approval->sum() / $approval->count();
        }

        return [
            'approval' => $this->roundToFloatLimit($approval),
            'passed'   => (int) $this->hasPassed($question, $approval),
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

        return [
            'approval' => $this->roundToFloatLimit($approvals->average()),
        ];
    }

    //////////////////////////////////////////////////////////////////////
    ////////////////////////////// HELPERS ///////////////////////////////
    //////////////////////////////////////////////////////////////////////

    /**
     * @param Question $question
     * @param float    $approval
     *
     * @return bool
     */
    public function hasPassed(Question $question, $approval)
    {
        return $approval > $this->getMajorityCondition($question);
    }

    /**
     * @param Question $question
     *
     * @return float
     */
    protected function getMajorityCondition(Question $question)
    {
        $majority  = 0.5;
        $condition = $question->request ? $question->request->condition : null;
        if (strpos($condition, '2/3') !== false) {
            $majority = 2 / 3;
        }

        return $majority;
    }

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
        $hivemind = 0;
        foreach ($user->votes as $vote) {
            $hivemind += (int) $vote->question->getMajorityChoiceAttribute() === $vote->choice;
        }

        // Compute number of correct choices over total questions
        $hivemind = $hivemind / $user->votes->count();

        return $hivemind;
    }

    /**
     * Round a float to its truncated SQL version to avoid
     * false dirty positives.
     *
     * @param float $number
     *
     * @return float
     */
    protected function roundToFloatLimit($number)
    {
        return round($number, 6);
    }
}
