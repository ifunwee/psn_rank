<?php
class LotteryInterface extends BaseInterface
{

    public function getLotteryListByCurrent()
    {
        $service = s('User');

        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $user_id = '';
        }

        $service      = s('Lottery');
        $result = $service->getLotteryListByCurrent($user_id);

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
            $user_id = '';
        }

        $service      = s('Lottery');
        $result = $service->getLotteryDetail($lottery_id, $user_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getMyLotteryTicketList()
    {
        $lottery_id = getParam('lottery_id');
        $service = s('User');
        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $service      = s('Lottery');
        $result = $service->getMyLotteryTicketList($lottery_id, $user_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function getLotteryTicketRank()
    {
        $lottery_id = getParam('lottery_id');
        $page = getParam('page', 1);


        $service      = s('Lottery');
        $result = $service->getLotteryTicketRank($lottery_id, $page);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

    public function receivePrize()
    {
        $service = s('User');
        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $lottery_id = getParam('lottery_id');
        $lottery_ticket = getParam('lottery_ticket');
        $name = getParam('name');
        $mobile = getParam('mobile');
        $address = getParam('address');


        $service      = s('Lottery');
        $service->receivePrize($lottery_id, $user_id, $lottery_ticket, $name, $mobile, $address);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess(null);
    }

    public function getContactInfo()
    {
        $service = s('User');
        $user_id = $service->getUserIdByToken(b('jwt'));
        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $service      = s('Lottery');
        $result = $service->getContactInfo($user_id);

        if ($service->hasError()) {
            $this->respondFailure($service->getError());
        }

        $this->respondSuccess($result);
    }

}