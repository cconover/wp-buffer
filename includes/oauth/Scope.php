<?php

/**
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Lesser General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Lesser General Public License for more details.
 *
 *  You should have received a copy of the GNU Lesser General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace fkooman\OAuth\Client;

class Scope
{
    /** @var array */
    private $scope;

    public function __construct($scope = null)
    {
        if (null === $scope) {
            $this->scope = array();
        } elseif (is_array($scope)) {
            $this->setScopeFromArray($scope);
        } elseif (is_string($scope)) {
            $this->setScopeFromString($scope);
        } else {
            throw new ScopeException("scope needs to be a string, array or null");
        }
    }

    private function setScopeFromArray(array $scope)
    {
        if (0 === count($scope)) {
            $this->scope = array();
        }
        foreach ($scope as $s) {
            if (!$this->validateScopeToken($s)) {
                throw new ScopeException(sprintf("invalid scope token '%s'", $s));
            }
        }
        // sort the scope
        sort($scope, SORT_STRING);

        // remove duplicates and recreate index
        $this->scope = array_values(array_unique($scope, SORT_STRING));
    }

    private function setScopeFromString($scope)
    {
        $scopeArray = explode(" ", $scope);

        $this->setScopeFromArray($scopeArray);
    }

    private function validateScopeToken($scopeToken)
    {
        if (!is_string($scopeToken) || 0 >= strlen($scopeToken)) {
            throw new ScopeException("scope token must be a non-empty string");
        }
        $scopeTokenRegExp = '/^(?:\x21|[\x23-\x5B]|[\x5D-\x7E])+$/';
        $result = preg_match($scopeTokenRegExp, $scopeToken);

        return 1 === $result;
    }

    public function isEmptyScope()
    {
        return 0 === count($this->scope);
    }

    public function hasScope(Scope $scope)
    {
        foreach ($scope->getScopeAsArray() as $s) {
            if (!in_array($s, $this->scope)) {
                return false;
            }
        }

        return true;
    }

    public function getScopeAsArray()
    {
        return $this->scope;
    }

    public function getScopeAsString()
    {
        if (0 === count($this->scope)) {
            return null;
        }

        return implode(" ", $this->scope);
    }
}
