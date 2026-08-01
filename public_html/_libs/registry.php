<?php
// Shim → SJ\Content\Registry (src/Content/Registry.php). SECURITY.md SEC-01/09.
function sj_registry(): array
{
    return \SJ\Content\Registry::all();
}

function sj_registry_entity(string $entity): ?array
{
    return \SJ\Content\Registry::entity($entity);
}
