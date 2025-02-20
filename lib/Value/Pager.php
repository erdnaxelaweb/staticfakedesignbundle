<?php
/*
 * staticfakedesignbundle.
 *
 * @package   DesignBundle
 *
 * @author    florian
 * @copyright 2023-present Florian ALEXANDRE
 * @license   https://github.com/erdnaxelaweb/staticfakedesign/blob/main/LICENSE
 */

namespace ErdnaxelaWeb\StaticFakeDesign\Value;

use Pagerfanta\Adapter\AdapterInterface;
use Pagerfanta\Exception\LessThan1CurrentPageException;
use Pagerfanta\Exception\LessThan1MaxPagesException;
use Pagerfanta\Exception\LessThan1MaxPerPageException;
use Pagerfanta\Exception\LogicException;
use Pagerfanta\Exception\NotBooleanException;
use Pagerfanta\Exception\NotIntegerCurrentPageException;
use Pagerfanta\Exception\NotIntegerException;
use Pagerfanta\Exception\NotIntegerMaxPerPageException;
use Pagerfanta\Exception\OutOfBoundsException;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\Form\FormView;

/**
 * @template T
 * @implements \IteratorAggregate<T>
 */
class Pager implements \Countable, \IteratorAggregate, \JsonSerializable, PagerfantaInterface
{
    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var bool
     */
    private $allowOutOfRangePages = false;

    /**
     * @var bool
     */
    private $normalizeOutOfRangePages = false;

    /**
     * @var int
     */
    private $maxPerPage = 10;

    /**
     * @var int
     */
    private $currentPage = 1;

    /**
     * @var int|null
     */
    private $nbResults;

    /**
     * @var int|null
     */
    private $maxNbPages;

    private $headlineCount = 0;

    /**
     * @var iterable<array-key, T>|null
     */
    private $currentPageResults;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param bool $allowOutOfRangePages
     *
     * @return $this
     */
    public function setAllowOutOfRangePages($allowOutOfRangePages)
    {
        $this->allowOutOfRangePages = $this->filterBoolean($allowOutOfRangePages);

        return $this;
    }

    /**
     * @return bool
     */
    public function getAllowOutOfRangePages()
    {
        return $this->allowOutOfRangePages;
    }

    /**
     * @param bool $normalizeOutOfRangePages
     *
     * @return $this
     */
    public function setNormalizeOutOfRangePages($normalizeOutOfRangePages)
    {
        $this->normalizeOutOfRangePages = $this->filterBoolean($normalizeOutOfRangePages);

        return $this;
    }

    /**
     * @return bool
     */
    public function getNormalizeOutOfRangePages()
    {
        return $this->normalizeOutOfRangePages;
    }

    /**
     * Sets the maximum number of items per page.
     *
     * Tries to convert from string and float.
     *
     * @param int $maxPerPage
     *
     * @return $this
     */
    public function setMaxPerPage($maxPerPage)
    {
        $this->maxPerPage = $maxPerPage === 0 ? $maxPerPage : $this->filterMaxPerPage($maxPerPage);
        $this->resetForMaxPerPageChange();

        return $this;
    }

    public function setHeadlineCount(int $headlineCount): void
    {
        $this->headlineCount = $headlineCount;
    }

    /**
     * @param int $maxPerPage
     */
    private function filterMaxPerPage($maxPerPage): int
    {
        $maxPerPage = $this->toInteger($maxPerPage);
        $this->checkMaxPerPage($maxPerPage);

        return $maxPerPage;
    }

    /**
     * @param int $maxPerPage
     */
    private function checkMaxPerPage($maxPerPage): void
    {
        if (! \is_int($maxPerPage)) {
            throw new NotIntegerMaxPerPageException();
        }

        if ($maxPerPage < 1) {
            throw new LessThan1MaxPerPageException();
        }
    }

    private function resetForMaxPerPageChange(): void
    {
        $this->currentPageResults = null;
    }

    public function isFirstPage(): bool
    {
        return ! $this->hasPreviousPage();
    }

    /**
     * @return int
     */
    public function getMaxPerPage()
    {
        return $this->maxPerPage;
    }

    /**
     * Sets the current page.
     *
     * Tries to convert from string and float.
     *
     * @param int $currentPage
     *
     * @return $this
     */
    public function setCurrentPage($currentPage)
    {
        if (\count(\func_get_args()) > 1) {
            $this->useDeprecatedCurrentPageBooleanArguments(\func_get_args());
        }

        $this->currentPage = $this->filterCurrentPage($currentPage);
        $this->resetForCurrentPageChange();

        return $this;
    }

    private function useDeprecatedCurrentPageBooleanArguments(array $arguments): void
    {
        $this->useDeprecatedCurrentPageAllowOutOfRangePagesBooleanArgument($arguments);
        $this->useDeprecatedCurrentPageNormalizeOutOfRangePagesBooleanArgument($arguments);
    }

    private function useDeprecatedCurrentPageAllowOutOfRangePagesBooleanArgument(array $arguments): void
    {
        $this->useDeprecatedBooleanArgument($arguments, 1, 'setAllowOutOfRangePages', '$allowOutOfRangePages');
    }

    private function useDeprecatedCurrentPageNormalizeOutOfRangePagesBooleanArgument(array $arguments): void
    {
        $this->useDeprecatedBooleanArgument($arguments, 2, 'setNormalizeOutOfRangePages', '$normalizeOutOfRangePages');
    }

    private function useDeprecatedBooleanArgument(
        array $arguments,
        int $index,
        string $method,
        string $oldArgument
    ): void {
        if (isset($arguments[$index])) {
            trigger_deprecation(
                'pagerfanta/pagerfanta',
                '2.2',
                'The %1$s argument of %2$s::setCurrentPage() is deprecated and will no longer be supported in 3.0. Use the %2$s::%3$s() method instead.',
                $oldArgument,
                self::class,
                self::class,
                $method
            );

            $this->$method($arguments[$index]);
        }
    }

    /**
     * @param int $currentPage
     */
    private function filterCurrentPage($currentPage): int
    {
        $currentPage = $this->toInteger($currentPage);
        $this->checkCurrentPage($currentPage);
        $currentPage = $this->filterOutOfRangeCurrentPage($currentPage);

        return $currentPage;
    }

    /**
     * @param int $currentPage
     */
    private function checkCurrentPage($currentPage): void
    {
        if (! \is_int($currentPage)) {
            throw new NotIntegerCurrentPageException();
        }

        if ($currentPage < 1) {
            throw new LessThan1CurrentPageException();
        }
    }

    /**
     * @param int $currentPage
     */
    private function filterOutOfRangeCurrentPage($currentPage): int
    {
        if ($this->notAllowedCurrentPageOutOfRange($currentPage)) {
            return $this->normalizeOutOfRangeCurrentPage($currentPage);
        }

        return $currentPage;
    }

    private function notAllowedCurrentPageOutOfRange(int $currentPage): bool
    {
        return ! $this->getAllowOutOfRangePages() && $this->currentPageOutOfRange($currentPage);
    }

    private function currentPageOutOfRange(int $currentPage): bool
    {
        return $currentPage > 1 && $currentPage > $this->getNbPages();
    }

    /**
     * @param int $currentPage
     */
    private function normalizeOutOfRangeCurrentPage($currentPage): int
    {
        if ($this->getNormalizeOutOfRangePages()) {
            return $this->getNbPages();
        }

        throw new OutOfRangeCurrentPageException(sprintf(
            'Page "%d" does not exist. The currentPage must be inferior to "%d"',
            $currentPage,
            $this->getNbPages()
        ));
    }

    private function resetForCurrentPageChange(): void
    {
        $this->currentPageResults = null;
    }

    /**
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * @return iterable<array-key, T>
     */
    public function getCurrentPageResults()
    {
        if (null === $this->currentPageResults) {
            $this->currentPageResults = $this->getCurrentPageResultsFromAdapter();
        }

        return $this->currentPageResults;
    }

    /**
     * @return iterable<array-key, T>
     */
    public function getHeadlineResults()
    {
        if (! $this->isFirstPage()) {
            return [];
        }
        return $this->adapter->getSlice(0, $this->headlineCount);
    }

    /**
     * @return iterable<array-key, T>
     */
    private function getCurrentPageResultsFromAdapter(): iterable
    {
        $offset = $this->calculateOffsetForCurrentPageResults();
        $length = $this->getMaxPerPage();
        return $this->adapter->getSlice($offset, $length);
    }

    private function calculateOffsetForCurrentPageResults(): int
    {
        $offset = (($this->getCurrentPage() - 1) * $this->getMaxPerPage()) + $this->headlineCount;
        return $offset;
    }

    /**
     * Calculates the current page offset start.
     *
     * @return int
     */
    public function getCurrentPageOffsetStart()
    {
        return $this->getNbResults() ? $this->calculateOffsetForCurrentPageResults() + 1 : 0;
    }

    /**
     * Calculates the current page offset end.
     *
     * @return int
     */
    public function getCurrentPageOffsetEnd()
    {
        return $this->hasNextPage() ? $this->getCurrentPage() * $this->getMaxPerPage() : $this->getNbResults();
    }

    /**
     * @return int
     */
    public function getNbResults()
    {
        if (null === $this->nbResults) {
            $this->nbResults = $this->getAdapter()
                ->getNbResults();
        }

        return $this->nbResults;
    }

    /**
     * @return int
     */
    public function getNbPages()
    {
        $nbPages = $this->calculateNbPages();

        if (0 === $nbPages) {
            return $this->minimumNbPages();
        }

        if (null !== $this->maxNbPages && $this->maxNbPages < $nbPages) {
            return $this->maxNbPages;
        }

        return $nbPages;
    }

    private function calculateNbPages(): int
    {
        $maxPerPage = $this->getMaxPerPage();
        if ($maxPerPage === 0) {
            return 0;
        }
        return (int) ceil(($this->getNbResults() - $this->headlineCount) / $maxPerPage);
    }

    private function minimumNbPages(): int
    {
        return 1;
    }

    /**
     * @return $this<T>
     */
    public function setMaxNbPages(int $maxNbPages): self
    {
        if ($maxNbPages < 1) {
            throw new LessThan1MaxPagesException();
        }

        $this->maxNbPages = $maxNbPages;

        return $this;
    }

    /**
     * @return $this<T>
     */
    public function resetMaxNbPages(): self
    {
        $this->maxNbPages = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function haveToPaginate()
    {
        return $this->getNbResults() > $this->maxPerPage;
    }

    /**
     * @return bool
     */
    public function hasPreviousPage()
    {
        return $this->currentPage > 1;
    }

    /**
     * @return int
     */
    public function getPreviousPage()
    {
        if (! $this->hasPreviousPage()) {
            throw new LogicException('There is no previous page.');
        }

        return $this->currentPage - 1;
    }

    /**
     * @return bool
     */
    public function hasNextPage()
    {
        return $this->currentPage < $this->getNbPages();
    }

    /**
     * @return int
     */
    public function getNextPage()
    {
        if (! $this->hasNextPage()) {
            throw new LogicException('There is no next page.');
        }

        return $this->currentPage + 1;
    }

    /**
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->getNbResults();
    }

    /**
     * @return \Traversable<array-key, T>
     */
    #[\ReturnTypeWillChange]
    public function getIterator()
    {
        $results = $this->getCurrentPageResults();

        if ($results instanceof \Iterator) {
            return $results;
        }

        if ($results instanceof \IteratorAggregate) {
            return $results->getIterator();
        }

        if (\is_array($results)) {
            return new \ArrayIterator($results);
        }

        throw new \InvalidArgumentException(sprintf(
            'Cannot create iterator with page results of type "%s".',
            get_debug_type($results)
        ));
    }

    /**
     * @return iterable
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $results = $this->getCurrentPageResults();

        if ($results instanceof \Traversable) {
            return iterator_to_array($results);
        }

        return $results;
    }

    /**
     * Get page number of the item at specified position (1-based index).
     *
     * @param int $position
     *
     * @return int
     */
    public function getPageNumberForItemAtPosition($position)
    {
        if (! \is_int($position)) {
            throw new NotIntegerException();
        }

        if ($this->getNbResults() < $position) {
            throw new OutOfBoundsException(sprintf(
                'Item requested at position %d, but there are only %d items.',
                $position,
                $this->getNbResults()
            ));
        }

        return (int) ceil($position / $this->getMaxPerPage());
    }

    /**
     * @param bool $value
     */
    private function filterBoolean($value): bool
    {
        if (! \is_bool($value)) {
            throw new NotBooleanException();
        }

        return $value;
    }

    /**
     * @param int|float|string $value
     *
     * @return int
     */
    private function toInteger($value)
    {
        if ($this->needsToIntegerConversion($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param int|float|string $value
     */
    private function needsToIntegerConversion($value): bool
    {
        return (\is_string($value) || \is_float($value)) && (int) $value == $value;
    }

    public function getActiveFilters(): array
    {
        return $this->getAdapter()
            ->getActiveFilters();
    }

    public function getFilters(): FormView
    {
        return $this->getAdapter()
            ->getFilters();
    }
}
