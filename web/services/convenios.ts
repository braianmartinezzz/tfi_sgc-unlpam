import { apiDownload, apiRequest } from "@/services/api-client";
import type { ApiMessageResponse, Paginated } from "@/types/api";
import type {
  Convenio,
  ConvenioCreateInput,
  ConvenioListFilters,
  ConvenioListItem,
  ConvenioMutationResponse,
  ConvenioResponse,
  ConvenioUpdateInput,
} from "@/types/convenio";
import type {
  DocumentoListResponse,
  DocumentoMutationResponse,
  DocumentoUploadInput,
  EnviarRevisionResponse,
} from "@/types/documento";

function buildQuery(filters: ConvenioListFilters): string {
  const params = new URLSearchParams();
  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") params.set(key, String(value));
  }
  const query = params.toString();
  return query ? `?${query}` : "";
}

export function list(
  filters: ConvenioListFilters = {},
): Promise<Paginated<ConvenioListItem>> {
  return apiRequest<Paginated<ConvenioListItem>>(
    `/convenios${buildQuery(filters)}`,
  );
}

export async function listAll(
  filters: Omit<ConvenioListFilters, "page"> = {},
): Promise<ConvenioListItem[]> {
  const items: ConvenioListItem[] = [];
  let page = 1;
  let lastPage = 1;

  do {
    const response = await list({ ...filters, page });
    items.push(...response.data);
    lastPage = response.last_page;
    page += 1;
  } while (page <= lastPage);

  return items;
}

export async function listMarcos(): Promise<ConvenioListItem[]> {
  return listAll({ es_marco: true });
}

export async function get(id: number | string): Promise<Convenio> {
  const response = await apiRequest<ConvenioResponse>(`/convenios/${id}`);
  return response.data;
}

export function create(
  input: ConvenioCreateInput,
): Promise<ConvenioMutationResponse> {
  return apiRequest<ConvenioMutationResponse>("/convenios", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export function update(
  id: number | string,
  input: ConvenioUpdateInput,
): Promise<ConvenioMutationResponse> {
  return apiRequest<ConvenioMutationResponse>(`/convenios/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export function listDocuments(
  id: number | string,
): Promise<DocumentoListResponse> {
  return apiRequest<DocumentoListResponse>(`/convenios/${id}/documentos`);
}

export function uploadDocument(
  id: number | string,
  input: DocumentoUploadInput,
): Promise<DocumentoMutationResponse> {
  const body = new FormData();
  body.append("tipo", input.tipo);
  body.append("titulo", input.titulo);
  body.append("archivo", input.archivo);

  return apiRequest<DocumentoMutationResponse>(`/convenios/${id}/documentos`, {
    method: "POST",
    body,
  });
}

export function updateDocument(
  id: number | string,
  input: DocumentoUploadInput,
): Promise<DocumentoMutationResponse> {
  const body = new FormData();
  body.append("_method", "PUT");
  body.append("tipo", input.tipo);
  body.append("titulo", input.titulo);
  body.append("archivo", input.archivo);

  return apiRequest<DocumentoMutationResponse>(`/documentos/${id}`, {
    method: "POST",
    body,
  });
}

export function updateDocumentDetails(
  id: number | string,
  input: Pick<DocumentoUploadInput, "tipo" | "titulo">,
): Promise<DocumentoMutationResponse> {
  const body = new FormData();
  body.append("_method", "PUT");
  body.append("tipo", input.tipo);
  body.append("titulo", input.titulo);

  return apiRequest<DocumentoMutationResponse>(`/documentos/${id}`, {
    method: "POST",
    body,
  });
}

export function deleteDocument(
  id: number | string,
): Promise<ApiMessageResponse> {
  return apiRequest<ApiMessageResponse>(`/documentos/${id}`, {
    method: "DELETE",
  });
}

export function sendForReview(
  id: number | string,
): Promise<EnviarRevisionResponse> {
  return apiRequest<EnviarRevisionResponse>(
    `/convenios/${id}/enviar-revision`,
    { method: "POST" },
  );
}

export function remove(
  id: number | string,
): Promise<ApiMessageResponse> {
  return apiRequest<ApiMessageResponse>(`/convenios/${id}`, {
    method: "DELETE",
  });
}

export function exportar(
  filters: Omit<ConvenioListFilters, "page"> = {},
): Promise<void> {
  return apiDownload(`/convenios/exportar${buildQuery(filters)}`);
}
