<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

namespace Eccube\Repository;

use Eccube\Annotation\Repository;
use Eccube\Entity\PluginEventHandler;
use Eccube\Exception\PluginException;

/**
 * PluginEventHandlerRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @Repository
 */
class PluginEventHandlerRepository extends AbstractRepository
{

    public function getHandlers()
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin('e.Plugin', 'p')
            ->andWhere('e.del_flg = 0 ')  
            ->Orderby('e.event','ASC') 
            ->addOrderby('e.priority','DESC');
            ;

        return $qb->getQuery()->getResult();
    }

    public function getPriorityRange($type)
    {

        if(PluginEventHandler::EVENT_HANDLER_TYPE_FIRST==$type){
            $range_start=PluginEventHandler::EVENT_PRIORITY_FIRST_START;
            $range_end=PluginEventHandler::EVENT_PRIORITY_FIRST_END;
        }elseif(PluginEventHandler::EVENT_HANDLER_TYPE_LAST==$type){
            $range_start=PluginEventHandler::EVENT_PRIORITY_LAST_START;
            $range_end=PluginEventHandler::EVENT_PRIORITY_LAST_END;
        }else{
            $range_start=PluginEventHandler::EVENT_PRIORITY_NORMAL_START;
            $range_end=PluginEventHandler::EVENT_PRIORITY_NORMAL_END;
        }
        return array($range_start,$range_end);

    }

    public function calcNewPriority($event , $type)
    {

        list($range_start,$range_end) = $this->getPriorityRange($type);

        $qb = $this->createQueryBuilder('e');
        $qb->andWhere("e.priority >= $range_end ")
           ->andWhere("e.priority <= $range_start ")
           ->andWhere('e.event = :event')
           ->setParameter('event',$event)
           ->setMaxResults(1)
           ->orderBy('e.priority','ASC');

        $result=$qb->getQuery()->getResult();
        if(count($result)){
             return $result[0]->getPriority() -1;
        }else{
             return $range_start;
        }

    }

    public function upPriority($pluginEventHandler,$up=true)
    {

        list($range_start,$range_end) = $this->getPriorityRange($pluginEventHandler->getHandlerType());

        $qb = $this->createQueryBuilder('e');

        $qb->andWhere("e.priority >= $range_end ")
           ->andWhere("e.priority <= $range_start ")
           ->andWhere("e.del_flg = 0 ") 
           ->andWhere('e.priority '.($up ?  '>' : '<' ).' :pri')
           ->andWhere('e.event = :event')
           ->setParameter('event',$pluginEventHandler->getEvent())
           ->setParameter('pri',  $pluginEventHandler->getPriority()  )
           ->setMaxResults(1)
           ->orderBy('e.priority', ($up ? 'ASC':'DESC' )  );

        $result=$qb->getQuery()->getResult();

        if(count($result)){
            $em =$this->getEntityManager();
            $em->getConnection()->beginTransaction();
            // 2個のentityのprioriryを入れ替える
            $tmp=$pluginEventHandler->getPriority();
            $pluginEventHandler->setPriority($result[0]->getPriority());
            $result[0]->setPriority($tmp);
            $em->persist($result[0]);
            $em->persist($pluginEventHandler);
            $em->flush();
            $em->getConnection()->commit();
            # 移動する
        }else{
            # 移動しない
            throw new PluginException("Can't swap");
        }


    }

}
