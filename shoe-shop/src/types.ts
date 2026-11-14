export interface Category {
  id: string;
  name: string;
  description: string;
}

export interface ProductColor {
  name: string;
  hex: string;
}

export interface Product {
  id: string;
  name: string;
  categoryId: string;
  /** Price in US dollars. */
  price: number;
  sizes: number[];
  colors: ProductColor[];
  description: string;
  featured: boolean;
}

export interface Catalog {
  categories: Category[];
  products: Product[];
}

/** One line in the cart. Price and name come from the catalogue when needed. */
export interface CartItem {
  productId: string;
  size: number;
  color: string;
  quantity: number;
}

export interface ShippingDetails {
  fullName: string;
  email: string;
  address: string;
  city: string;
  state: string;
  postalCode: string;
}

export interface OrderTotals {
  subtotalCents: number;
  shippingCents: number;
  taxCents: number;
  totalCents: number;
}

export interface OrderLine {
  productId: string;
  name: string;
  size: number;
  color: string;
  quantity: number;
  unitPriceCents: number;
}

export interface Order {
  orderNumber: string;
  createdAt: string;
  lines: OrderLine[];
  totals: OrderTotals;
  shipping: ShippingDetails;
  transactionId: string;
  cardLast4: string;
}
