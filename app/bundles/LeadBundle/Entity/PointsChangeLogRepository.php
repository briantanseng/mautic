<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Doctrine\ORM\Query;

/**
 * PointsChangeLogRepository
 */
class PointsChangeLogRepository extends CommonRepository
{

    /**
     * Fetch Lead's points for some period of time.
     * 
     * @param integer $leadId
     * @param integer $quantity of units
     * @param string $unit of time php.net/manual/en/class.dateinterval.php#dateinterval.props
     *
     * @return mixed
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLeadPoints($leadId, $quantity, $unit)
    {
        $values = array();
        $labels = array();
        $date = new \DateTime();
        $timeInterval = new \DateInterval('P1'.$unit);
        $labelFormat = 'F'; // @TODO: F = month name. Must be different for days, weeks, years
        
        // Create Labels array
        for ($i = 0; $i < $quantity; $i++) {
            $labels[] = $date->format($labelFormat);
            $date->sub($timeInterval);
        }

        // Load points for selected period
        $q = $this->createQueryBuilder('pl');
        $q->select('pl.delta, pl.dateAdded')
            ->where($q->expr()->eq('IDENTITY(pl.lead)', ':lead'))
            ->setParameter('lead', $leadId)
            ->andwhere($q->expr()->gte('pl.dateAdded', ':date'))
            ->setParameter('date', $date)
            ->orderBy('pl.dateAdded', 'DESC');

        $points = $q->getQuery()->getArrayResult();

        // Count total
        $q2 = $this->createQueryBuilder('pl');
        $q2->select('sum(pl.delta) as total')
            ->where($q->expr()->eq('IDENTITY(pl.lead)', ':lead'))
            ->setParameter('lead', $leadId);

        $total = $q2->getQuery()->getSingleResult();
        $total = (int) $total['total'];

        // Calculate points for months.
        foreach ($points as $point) {
            $key = array_search($point['dateAdded']->format($labelFormat), $labels);
            $values[$key] = $total;
            $total -= $point['delta'];
        }

        // Populate all months
        for ($i = ($quantity - 1); $i >= 0; $i--) {
            if (isset($values[$i])) {
                $total = $values[$i];
            } else {
                $values[$i] = $total;
            }
        }

        ksort($values);

        return array('labels' => array_reverse($labels), 'data' => array_reverse($values));
    }
}