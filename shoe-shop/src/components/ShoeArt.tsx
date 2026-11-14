interface ShoeArtProps {
  /** Category id: decides the shoe's silhouette. */
  categoryId: string;
  /** Main colour of the upper. */
  color: string;
  /** Accent colour for trims and soles. */
  accent?: string;
  /** Accessible name. Pass an empty string for decorative use. */
  label?: string;
}

/**
 * A simple side-view shoe drawn in SVG. The shape depends on the category
 * and the colours come from the product.
 */
export function ShoeArt({ categoryId, color, accent = '#1f1f24', label = 'Shoe' }: ShoeArtProps) {
  const decorative = label === '';
  return (
    <svg
      className="shoe-art"
      viewBox="0 0 200 130"
      role={decorative ? undefined : 'img'}
      aria-label={decorative ? undefined : label}
      aria-hidden={decorative ? true : undefined}
      focusable="false"
    >
      <rect width="200" height="130" rx="14" className="shoe-art__bg" />
      <ellipse cx="100" cy="116" rx="78" ry="6" className="shoe-art__shadow" />
      {renderShape(categoryId, color, accent)}
    </svg>
  );
}

function renderShape(categoryId: string, color: string, accent: string) {
  switch (categoryId) {
    case 'running':
      return (
        <g>
          <path d="M18 88 C22 58 48 44 78 46 L110 58 C132 66 150 70 170 84 L182 92 L182 104 L18 104 Z" fill={color} />
          <path d="M30 94 Q92 82 172 92" fill="none" stroke={accent} strokeWidth="4" strokeLinecap="round" />
          <path d="M14 100 H186 V112 Q186 118 180 118 H20 Q14 118 14 112 Z" fill="#f4f4f5" />
          <g stroke="#ffffff" strokeWidth="3" strokeLinecap="round">
            <line x1="78" y1="54" x2="92" y2="60" />
            <line x1="86" y1="50" x2="100" y2="56" />
          </g>
        </g>
      );
    case 'casual':
      return (
        <g>
          <path d="M22 98 V56 Q22 42 38 42 H64 Q76 66 118 70 Q158 72 178 92 V104 H22 Z" fill={color} />
          <path d="M22 62 Q42 68 66 56" fill="none" stroke={accent} strokeWidth="5" strokeLinecap="round" />
          <path d="M22 96 H178 V106 Q178 112 172 112 H28 Q22 112 22 106 Z" fill="#f4f4f5" />
          <g stroke="#ffffff" strokeWidth="3" strokeLinecap="round">
            <line x1="64" y1="62" x2="76" y2="56" />
            <line x1="84" y1="66" x2="96" y2="60" />
          </g>
        </g>
      );
    case 'formal':
      return (
        <g>
          <path d="M16 96 C24 70 52 60 84 62 C112 64 128 74 150 76 C166 78 178 86 184 96 V104 H16 Z" fill={color} />
          <path d="M168 80 Q186 86 184 98 H160 Q168 90 168 80 Z" fill={accent} />
          <g stroke="#ffffff" strokeWidth="2.5" strokeLinecap="round" opacity="0.7">
            <line x1="70" y1="66" x2="84" y2="72" />
            <line x1="84" y1="66" x2="98" y2="72" />
            <line x1="98" y1="66" x2="112" y2="72" />
          </g>
          <path d="M14 102 H186 V110 Q186 114 182 114 H18 Q14 114 14 110 Z" fill="#27272a" />
        </g>
      );
    case 'sandals':
      return (
        <g>
          <path d="M24 104 Q100 98 176 104 Q184 108 176 116 Q100 122 24 116 Q16 110 24 104 Z" fill={accent} />
          <path d="M40 104 Q60 62 100 60 Q140 62 162 104" fill="none" stroke={color} strokeWidth="7" strokeLinecap="round" />
          <path d="M86 61 L98 104" fill="none" stroke={color} strokeWidth="6" strokeLinecap="round" />
          <path d="M116 61 L104 104" fill="none" stroke={color} strokeWidth="6" strokeLinecap="round" />
        </g>
      );
    default:
      return (
        <path d="M22 98 V56 Q22 42 38 42 H64 Q76 66 118 70 Q158 72 178 92 V104 H22 Z" fill={color} />
      );
  }
}
