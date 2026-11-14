export interface ParsedRoute {
  /** Path parts, e.g. "#/product/run-sprint-lite" gives ["product", "run-sprint-lite"]. */
  segments: string[];
  query: URLSearchParams;
}

function safeDecode(part: string): string {
  try {
    return decodeURIComponent(part);
  } catch {
    return part;
  }
}

/** Reads a location hash such as "#/shop?category=running&size=9". */
export function parseHash(hash: string): ParsedRoute {
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;
  const [pathPart, queryPart = ''] = raw.split('?');
  const segments = pathPart
    .split('/')
    .filter((part) => part.length > 0)
    .map(safeDecode);
  return { segments, query: new URLSearchParams(queryPart) };
}

/** Builds a hash for segments and an optional query: buildHash(['shop'], q) gives "#/shop?...". */
export function buildHash(segments: string[], query?: URLSearchParams): string {
  const path = segments.map((segment) => encodeURIComponent(segment)).join('/');
  const queryText = query && query.toString() ? `?${query.toString()}` : '';
  return `#/${path}${queryText}`;
}
