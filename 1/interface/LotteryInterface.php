<?php
class LotteryInterface extends BaseInterface
{

    public function getLotteryListByCurrent()
    {
        $service      = s('Lottery');
        $result = $service->getLotteryListByCurrent();

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getLotteryListByHistory()
    {
        $page = getParam('page');
        $service      = s('Lottery');
        $result = $service->getLotteryListByHistory($page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getLotteryTicket()
    {
        $lottery_id = getParam('lottery_id');
        $service = s('User');
        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $service      = s('Lottery');
        $result = $service->getLotteryTicket($user_id, $lottery_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getLotteryDetail()
    {
        $lottery_id = getParam('lottery_id');
        $service = s('User');
        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $service      = s('Lottery');
        $result = $service->getLotteryDetail($lottery_id, $user_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

}