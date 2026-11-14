import { useEffect, useState } from 'react';
import { parseHash, type ParsedRoute } from './router';

function currentRoute(): ParsedRoute {
  return parseHash(window.location.hash);
}

/** Returns the current hash route and re-renders when it changes. */
export function useRoute(): ParsedRoute {
  const [route, setRoute] = useState<ParsedRoute>(currentRoute);

  useEffect(() => {
    const onChange = () => setRoute(currentRoute());
    window.addEventListener('hashchange', onChange);
    return () => window.removeEventListener('hashchange', onChange);
  }, []);

  return route;
}

/** Moves to a hash route such as "#/shop". */
export function navigate(hash: string): void {
  if (window.location.hash === hash) return;
  window.location.hash = hash;
}
